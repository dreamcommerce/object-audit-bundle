<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use DreamCommerce\Bundle\ObjectAuditBundle\BaseObjectAuditManager;
use DreamCommerce\Component\ObjectAudit\Collection\AuditedCollection;
use DreamCommerce\Component\ObjectAudit\Exception\DeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\NoRevisionFoundException;
use DreamCommerce\Component\ObjectAudit\Exception\NotAuditedException;
use DreamCommerce\Component\ObjectAudit\Model\ChangedObject;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use RuntimeException;

class ORMAuditManager extends BaseObjectAuditManager
{
    /**
     * Entity cache to prevent circular references.
     *
     * @var array
     */
    protected $entityCache = [];

    /**
     * {@inheritdoc}
     */
    public function findObjectByRevision(string $className, $objectId, RevisionInterface $revision, ObjectManager $objectManager = null, array $options = [])
    {
        $options = array_merge(['threatDeletionsAsExceptions' => false], $options);

        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        /** @var ClassMetadata $entityMetadata */
        $entityMetadata = $objectManager->getClassMetadata($className);
        /** @var EntityManagerInterface $auditObjectManager */
        $auditObjectManager = $this->getAuditObjectManager();
        $revisionMetadata = $auditObjectManager->getClassMetadata($this->revisionClass);
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();
        $quoteStrategy = $auditObjectManager->getConfiguration()->getQuoteStrategy();
        $connection = $auditObjectManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $tableName = $this->getAuditTableNameForClass($className, $auditObjectManager);

        if (!is_array($objectId)) {
            $objectId = array($entityMetadata->identifier[0] => $objectId);
        }

        $bindParameters = [];
        $columns = [];
        $conditions = [];

        foreach ($revisionMetadata->identifier as $revisionIdField) {
            if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
            } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
            } else {
                continue;
            }
            $columnName = $configuration->getRevisionIdFieldPrefix().$columnName.$configuration->getRevisionIdFieldSuffix();
            $bindParameters[] = $columnName;
            $columns[] = 'e.'.$columnName;
            $conditions[] = 'e.'.$columnName.' <= :'.$columnName;
        }
        $orders = array_map(function ($order) {
            return $order.' DESC';
        }, $columns);

        foreach ($entityMetadata->identifier as $idField) {
            if (isset($entityMetadata->fieldMappings[$idField])) {
                $columnName = $entityMetadata->fieldMappings[$idField]['columnName'];
            } elseif (isset($entityMetadata->associationMappings[$idField])) {
                $columnName = $entityMetadata->associationMappings[$idField]['joinColumns'][0];
            } else {
                continue;
            }
            $bindParameters[] = $columnName;
            $columns[] = 'e.'.$columnName;
            $conditions[] = 'e.'.$columnName.' = :'.$columnName;
        }

        $columnMap = array();

        foreach ($entityMetadata->fieldNames as $columnName => $field) {
            $tableAlias = $entityMetadata->isInheritanceTypeJoined() && $entityMetadata->isInheritedField($field) && !$entityMetadata->isIdentifier($field)
                ? 're' // root entity
                : 'e';

            $columns[] = sprintf(
                '%s.%s AS %s',
                $tableAlias,
                $quoteStrategy->getColumnName($field, $entityMetadata, $platform),
                $platform->quoteSingleIdentifier($field)
            );
            $columnMap[$field] = $platform->getSQLResultCasing($columnName);
        }

        foreach ($entityMetadata->associationMappings as $assoc) {
            if (($assoc['type'] & ClassMetadata::TO_ONE) == 0 || !$assoc['isOwningSide']) {
                continue;
            }

            foreach ($assoc['joinColumnFieldNames'] as $sourceCol) {
                $tableAlias = $entityMetadata->isInheritanceTypeJoined() &&
                $entityMetadata->isInheritedAssociation($assoc['fieldName']) &&
                !$entityMetadata->isIdentifier($assoc['fieldName'])
                    ? 're' // root entity
                    : 'e';
                $columns[] = $tableAlias.'.'.$sourceCol;
                $columnMap[$sourceCol] = $platform->getSQLResultCasing($sourceCol);
            }
        }

        $joinSql = '';
        if ($entityMetadata->isInheritanceTypeJoined() && $entityMetadata->name != $entityMetadata->rootEntityName) {
            /** @var ClassMetadata $rootClass */
            $rootClass = $objectManager->getClassMetadata($entityMetadata->rootEntityName);
            $rootTableName = $this->getAuditTableNameForClass($rootClass, $objectManager);
            $joinSql = "INNER JOIN {$rootTableName} re ON";
            $joinSqlConditions = [];

            foreach ($revisionMetadata->identifier as $revisionIdField) {
                if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                    $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
                } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                    $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
                } else {
                    continue;
                }
                $columnName = $configuration->getRevisionIdFieldPrefix().$columnName.$configuration->getRevisionIdFieldSuffix();
                $joinSqlConditions[] = 're.'.$columnName.' = e.'.$columnName;
            }

            foreach ($entityMetadata->getIdentifierColumnNames() as $name) {
                $joinSqlConditions[] = 're.'.$name.' = e.'.$name;
            }

            $joinSql .= ' '.implode(' AND ', $joinSqlConditions);
        }

        if (!$entityMetadata->isInheritanceTypeNone()) {
            $columns[] = $entityMetadata->discriminatorColumn['name'];
            if ($entityMetadata->isInheritanceTypeSingleTable()
                && $entityMetadata->discriminatorValue !== null) {

                // Support for single table inheritance sub-classes
                $allDiscrValues = array_flip($entityMetadata->discriminatorMap);
                $queriedDiscrValues = [$connection->quote($entityMetadata->discriminatorValue)];
                foreach ($entityMetadata->subClasses as $subclassName) {
                    $queriedDiscrValues[] = $connection->quote($allDiscrValues[$subclassName]);
                }

                $conditions[] = $entityMetadata->discriminatorColumn['name'].' IN '.'('.implode(', ', $queriedDiscrValues).')';
            }
        }

        $revisionIds = $revisionMetadata->getIdentifierValues($revision);
        foreach ($revisionIds as $fieldName => $fieldValue) {
            $newFieldName = $configuration->getRevisionIdFieldPrefix().$fieldName.$configuration->getRevisionIdFieldSuffix();
            $revisionIds[$newFieldName] = $fieldValue;
            unset($revisionIds[$fieldName]);
        }

        $bindValues = array_merge(
            $revisionIds,
            $objectId
        );

        $query = 'SELECT '.implode(', ', $columns).' '.
                    'FROM '.$tableName.' e '.
                    $joinSql.' '.
                    'WHERE '.implode(' AND ', $conditions).' '.
                    'ORDER BY '.implode(', ', $orders).' '.
                    'LIMIT 1';

        $stmt = $connection->prepare($query);
        foreach ($bindParameters as $bindParameter) {
            $stmt->bindValue($bindParameter, $bindValues[$bindParameter]);
        }
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NoRevisionFoundException($entityMetadata->name, $objectId, $revision);
        }

        if ($options['threatDeletionsAsExceptions'] && $row[$configuration->getRevisionTypeFieldName()] == RevisionInterface::ACTION_DELETE) {
            throw new DeletedException($entityMetadata->name, $objectId, $revision);
        }

        unset($row[$configuration->getRevisionTypeFieldName()]);

        return $this->createEntity($entityMetadata->name, $columnMap, $row, $revision, $revisionIds);
    }

    /**
     * {@inheritdoc}
     */
    public function findObjectsChangedAtRevision(string $className, RevisionInterface $revision, ObjectManager $objectManager = null, array $options = []): array
    {
        /** @var EntityManagerInterface $objectManager */
        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        $changedEntities = array();
        /** @var ClassMetadataInfo|ClassMetadata $class */
        $class = $objectManager->getClassMetadata($className);

        if ($class->isInheritanceTypeSingleTable() && count($class->subClasses) > 0) {
            return [];
        }

        $connection = $objectManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $quoteStrategy = $objectManager->getConfiguration()->getQuoteStrategy();
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();
        /** @var EntityManagerInterface $auditObjectManager */
        $auditObjectManager = $this->getAuditObjectManager();
        $revisionMetadata = $auditObjectManager->getClassMetadata($this->revisionClass);

        $tableName = $this->getAuditTableNameForClass($className);
        $bindParameters = [];
        $conditions = [];
        $revisionIds = $revisionMetadata->getIdentifierValues($revision);
        $bindValues = [];

        foreach ($revisionIds as $fieldName => $fieldValue) {
            $fieldName = $configuration->getRevisionIdFieldPrefix().$fieldName.$configuration->getRevisionIdFieldSuffix();
            $bindValues[$fieldName] = $fieldValue;
        }

        foreach ($revisionMetadata->identifier as $revisionIdField) {
            if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
            } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
            } else {
                continue;
            }
            $columnName = $configuration->getRevisionIdFieldPrefix().$columnName.$configuration->getRevisionIdFieldSuffix();
            $bindParameters[] = $columnName;
            $conditions[] = 'e.'.$columnName.' = :'.$columnName;
        }

        $columnList = ['e.'.$configuration->getRevisionTypeFieldName()];
        $columnMap = [];

        foreach ($class->fieldNames as $columnName => $field) {
            $tableAlias = $class->isInheritanceTypeJoined() && $class->isInheritedField($field) && !$class->isIdentifier($field)
                ? 're' // root entity
                : 'e';

            $columnList[] = $tableAlias.'.'.$quoteStrategy->getColumnName($field, $class, $platform).' AS '.$platform->quoteSingleIdentifier($field);
            $columnMap[$field] = $platform->getSQLResultCasing($columnName);
        }

        foreach ($class->associationMappings as $assoc) {
            if (($assoc['type'] & ClassMetadata::TO_ONE) > 0 && $assoc['isOwningSide']) {
                foreach ($assoc['targetToSourceKeyColumns'] as $sourceCol) {
                    $columnList[] = $sourceCol;
                    $columnMap[$sourceCol] = $platform->getSQLResultCasing($sourceCol);
                }
            }
        }

        $joinSql = '';
        if ($class->isInheritanceTypeSingleTable()) {
            $columnName = $class->discriminatorColumn['fieldName'];
            $columnList[] = 'e.'.$class->discriminatorColumn['name'];
            $conditions[] = 'e.'.$columnName.' = :'.$columnName;
            $bindParameters[] = $columnName;
            $bindValues[$columnName] = $class->discriminatorValue;
        } elseif ($class->isInheritanceTypeJoined() && $class->rootEntityName != $class->name) {
            $columnList[] = 're.'.$class->discriminatorColumn['name'];

            /** @var ClassMetadataInfo $rootClass */
            $rootClass = $objectManager->getClassMetadata($class->rootEntityName);
            $rootTableName = $this->getAuditTableNameForClass($rootClass, $objectManager);

            $joinSql = "INNER JOIN {$rootTableName} re ON";
            $joinSqlConditions = [];

            foreach ($revisionMetadata->identifier as $revisionIdField) {
                if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                    $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
                } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                    $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
                } else {
                    continue;
                }
                $columnName = $configuration->getRevisionIdFieldPrefix().$columnName.$configuration->getRevisionIdFieldSuffix();
                $joinSqlConditions[] = 're.'.$columnName.' = e.'.$columnName;
            }

            foreach ($class->getIdentifierColumnNames() as $name) {
                $joinSqlConditions[] = 're.'.$name.' = e.'.$name;
            }

            $joinSql .= ' '.implode(' AND ', $joinSqlConditions);
        }

        $query = 'SELECT '.implode(', ', $columnList).' '.
                    'FROM '.$tableName.' e '.
                    $joinSql.' '.
                    'WHERE '.implode(' AND ', $conditions);

        $stmt = $objectManager->getConnection()->prepare($query);
        foreach ($bindParameters as $bindParameter) {
            $stmt->bindValue($bindParameter, $bindValues[$bindParameter]);
        }
        $stmt->execute();
        $revisionsData = $stmt->fetchAll();

        $revisionFieldType = $configuration->getRevisionTypeFieldType();
        $revisionFieldName = $configuration->getRevisionTypeFieldName();
        $revisionType = null;
        if (Type::hasType($revisionFieldType)) {
            $revisionType = Type::getType($revisionFieldType);
        }

        foreach ($revisionsData as $row) {
            $id = [];

            foreach ($class->identifier as $idField) {
                $id[$idField] = $row[$idField];
            }

            $entity = $this->createEntity(
                $className,
                $columnMap,
                $row,
                $revision,
                $revisionIds,
                $objectManager
            );

            $objectRevType = $row[$revisionFieldName];
            if ($revisionType !== null) {
                $objectRevType = $revisionType->convertToPHPValue($objectRevType, $platform);
            }

            $changedEntities[] = new ChangedObject(
                $entity,
                $objectRevType
            );
        }

        return $changedEntities;
    }

    /**
     * {@inheritdoc}
     */
    public function findObjectRevisions(string $className, $objectId, ObjectManager $objectManager = null): Collection
    {
        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        /** @var EntityManagerInterface $auditManager */
        $auditManager = $this->getAuditObjectManager();
        $revisionMetadata = $auditManager->getClassMetadata($this->revisionClass);
        $revisionTableName = $revisionMetadata->getTableName();
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();

        /** @var ClassMetadata $entityMetadata */
        $entityMetadata = $objectManager->getClassMetadata($className);
        $entityTableName = $this->getAuditTableNameForClass($className, $objectManager);

        if (!is_array($objectId)) {
            $objectId = array($entityMetadata->identifier[0] => $objectId);
        }

        $conditions = [];
        $columns = [];
        foreach ($entityMetadata->identifier as $entityIdField) {
            if (isset($entityMetadata->fieldMappings[$entityIdField])) {
                $columnName = $entityMetadata->fieldMappings[$entityIdField]['columnName'];
            } elseif (isset($entityMetadata->associationMappings[$entityIdField])) {
                $columnName = $entityMetadata->associationMappings[$entityIdField]['joinColumns'][0];
            } else {
                continue;
            }
            $conditions[] = 'e.'.$columnName.' = :'.$columnName;
            $columns[] = $columnName;
        }

        $orders = [];
        $innerConditions = [];
        foreach ($revisionMetadata->identifier as $revisionIdField) {
            if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
            } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
            } else {
                continue;
            }
            $innerConditions[] = 'r.'.$revisionIdField.' = e.'.$configuration->getRevisionIdFieldPrefix().$columnName.$configuration->getRevisionIdFieldSuffix();
            $orders[] = 'r.'.$columnName.' DESC';
        }

        $sql = 'SELECT r.* '.
                    'FROM '.$revisionTableName.' r '.
                    'INNER JOIN '.$entityTableName.' e  ON '.implode(' AND ', $innerConditions).' '.
                    'WHERE '.implode(' AND ', $conditions).' '.
                    'ORDER BY '.implode(', ', $orders);

        $rsm = new ResultSetMappingBuilder($auditManager);
        $rsm->addRootEntityFromClassMetadata($this->revisionClass, 'r');
        $query = $auditManager->createNativeQuery($sql, $rsm);
        foreach ($columns as $column) {
            $query->setParameter($column, $objectId[$column]);
        }

        $result = $query->getResult();

        return new ArrayCollection($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getInitializeObjectRevision(string $className, $objectId, ObjectManager $objectManager = null)
    {
        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        return $this->getObjectRevision($className, $objectId, $objectManager);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentObjectRevision(string $className, $objectId, ObjectManager $objectManager = null)
    {
        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        return $this->getObjectRevision($className, $objectId, $objectManager, 'DESC');
    }

    /**
     * {@inheritdoc}
     */
    public function saveObjectRevisionData(ChangedObject $changedObject, ObjectManager $objectManager = null)
    {
        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        // TODO
    }

    /**
     * @param string             $className
     * @param ObjectManager|null $objectManager
     *
     * @return string
     */
    public function getAuditTableNameForClass(string $className, ObjectManager $objectManager = null)
    {
        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        /** @var ClassMetadata $metadata */
        $metadata = $objectManager->getClassMetadata($className);
        $tableName = $metadata->getTableName();
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();

        return $configuration->getTablePrefix().$tableName.$configuration->getTableSuffix();
    }

    /**
     * @param string        $className
     * @param mixed         $objectId
     * @param ObjectManager $objectManager
     * @param string        $sort
     *
     * @return null|object
     */
    private function getObjectRevision(string $className, $objectId, ObjectManager $objectManager, $sort = 'ASC')
    {
        /** @var ClassMetadataInfo $entityMetadata */
        $entityMetadata = $objectManager->getClassMetadata($className);
        $entityTableName = $this->getAuditTableNameForClass($className, $objectManager);
        /** @var EntityManagerInterface $auditEntityManager */
        $auditEntityManager = $this->getAuditObjectManager();
        $revisionMetadata = $auditEntityManager->getClassMetadata($this->revisionClass);
        $connection = $auditEntityManager->getConnection();
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();

        if (!is_array($objectId)) {
            $objectId = [$entityMetadata->identifier[0] => $objectId];
        }

        $columns = [];
        $conditions = [];
        foreach ($entityMetadata->identifier as $idField) {
            if (isset($entityMetadata->fieldMappings[$idField])) {
                $columnName = $entityMetadata->fieldMappings[$idField]['columnName'];
            } elseif (isset($entityMetadata->associationMappings[$idField])) {
                $columnName = $entityMetadata->associationMappings[$idField]['joinColumns'][0];
            } else {
                continue;
            }

            $columns[] = $columnName;
            $conditions[] = $columnName.' = :'.$columnName;
        }

        $revisionIdMap = [];
        $revisionIdNames = [];
        foreach ($revisionMetadata->identifier as $idField) {
            $revisionIdName = $configuration->getRevisionIdFieldPrefix().$idField.$configuration->getRevisionIdFieldSuffix();
            $revisionIdNames[] = $revisionIdName;
            $revisionIdMap[$revisionIdName] = $idField;
        }
        $revisionIdOrders = array_map(function ($idField) use ($sort) {
            return $idField.' '.$sort;
        }, $revisionIdNames);

        $query = 'SELECT '.implode(', ', $revisionIdNames).' '.
            'FROM '.$entityTableName.' '.
            'WHERE '.implode(' AND ', $conditions).' '.
            'ORDER BY '.implode(', ', $revisionIdOrders).' '.
            'LIMIT 1';

        $stmt = $connection->prepare($query);
        foreach ($columns as $column) {
            $stmt->bindValue($column, $objectId[$column]);
        }
        $stmt->execute();
        $revisionIds = $stmt->fetch(\PDO::FETCH_ASSOC);

        foreach ($revisionIds as $fieldName => $fieldValue) {
            $newFieldName = $revisionIdMap[$fieldName];
            $revisionIds[$newFieldName] = $fieldValue;
            unset($revisionIds[$fieldName]);
        }

        return $this->getRevisionRepository()->find($revisionIds);
    }

    /**
     * Simplified and stolen code from UnitOfWork::createEntity.
     *
     * @param string                      $className
     * @param array                       $columnMap
     * @param array                       $data
     * @param RevisionInterface           $revision
     * @param array                       $revisionIds
     * @param EntityManagerInterface|null $objectManager
     *
     * @throws DeletedException
     * @throws NoRevisionFoundException
     * @throws NotAuditedException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\Mapping\MappingException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     *
     * @return object
     */
    protected function createEntity($className, array $columnMap, array $data, RevisionInterface $revision, array $revisionIds, EntityManagerInterface $objectManager = null)
    {
        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        $revisionToken = implode('_', array_values($revisionIds));

        /** @var ClassMetadata $class */
        $class = $objectManager->getClassMetadata($className);
        $connection = $objectManager->getConnection();

        //lookup revisioned entity cache
        $keyParts = array();

        foreach ($class->getIdentifierFieldNames() as $name) {
            $keyParts[] = $data[$name];
        }

        $key = implode(':', $keyParts);

        if (isset($this->entityCache[$className][$key][$revisionToken])) {
            return $this->entityCache[$className][$key][$revisionToken];
        }

        if (!$class->isInheritanceTypeNone()) {
            if (!isset($data[$class->discriminatorColumn['name']])) {
                throw new RuntimeException('Expecting discriminator value in data set.');
            }
            $discriminator = $data[$class->discriminatorColumn['name']];
            if (!isset($class->discriminatorMap[$discriminator])) {
                throw new RuntimeException("No mapping found for [{$discriminator}].");
            }

            if ($class->discriminatorValue) {
                $entity = $objectManager->getClassMetadata($class->discriminatorMap[$discriminator])->newInstance();
            } else {
                //a complex case when ToOne binding is against AbstractEntity having no discriminator
                $pk = array();

                foreach ($class->identifier as $field) {
                    $pk[$class->getColumnName($field)] = $data[$field];
                }

                return $this->findObjectByRevision($class->discriminatorMap[$discriminator], $pk, $revision, $objectManager);
            }
        } else {
            $entity = $class->newInstance();
        }

        if (!isset($this->entityCache[$className])) {
            $this->entityCache[$className] = [];

            if (!isset($this->entityCache[$className][$key])) {
                $this->entityCache[$className][$key] = [];
            }
        }

        //cache the entity to prevent circular references
        $this->entityCache[$className][$key][$revisionToken] = $entity;

        foreach ($data as $field => $value) {
            if (isset($class->fieldMappings[$field])) {
                $value = $connection->convertToPHPValue($value, $class->fieldMappings[$field]['type']);
                $class->reflFields[$field]->setValue($entity, $value);
            }
        }

        $uow = $objectManager->getUnitOfWork();
        $configuration = $this->getConfiguration();

        foreach ($class->associationMappings as $field => $assoc) {
            // Check if the association is not among the fetch-joined associations already.
            if (isset($hints['fetched'][$className][$field])) {
                continue;
            }

            /** @var ClassMetadata $targetClass */
            $targetClass = $objectManager->getClassMetadata($assoc['targetEntity']);

            if ($assoc['type'] & ClassMetadata::TO_ONE) {
                if ($configuration->isClassAudited($assoc['targetEntity'])) {
                    if ($configuration->isLoadAuditedEntities()) {
                        $pk = array();

                        if ($assoc['isOwningSide']) {
                            foreach ($assoc['targetToSourceKeyColumns'] as $foreign => $local) {
                                $pk[$foreign] = $data[$columnMap[$local]];
                            }
                        } else {
                            /** @var ClassMetadata $otherEntityMeta */
                            $otherEntityMeta = $objectManager->getClassMetadata($assoc['targetEntity']);

                            foreach ($otherEntityMeta->associationMappings[$assoc['mappedBy']]['targetToSourceKeyColumns'] as $local => $foreign) {
                                $pk[$foreign] = $data[$class->getFieldName($local)];
                            }
                        }

                        $pk = array_filter($pk, function ($value) {
                            return !is_null($value);
                        });

                        if (!$pk) {
                            $class->reflFields[$field]->setValue($entity, null);
                        } else {
                            try {
                                $value = $this->findObjectByRevision($targetClass->name, $pk, $revision, $objectManager, ['threatDeletionsAsExceptions' => true]);
                            } catch (DeletedException $e) {
                                $value = null;
                            } catch (NoRevisionFoundException $e) {
                                // The entity does not have any revision yet. So let's get the actual state of it.
                                $value = $objectManager->find($targetClass->name, $pk);
                            }

                            $class->reflFields[$field]->setValue($entity, $value);
                        }
                    } else {
                        $class->reflFields[$field]->setValue($entity, null);
                    }
                } else {
                    if ($configuration->isLoadNativeEntities()) {
                        if ($assoc['isOwningSide']) {
                            $associatedId = array();
                            foreach ($assoc['targetToSourceKeyColumns'] as $targetColumn => $srcColumn) {
                                $joinColumnValue = isset($data[$columnMap[$srcColumn]]) ? $data[$columnMap[$srcColumn]] : null;
                                if ($joinColumnValue !== null) {
                                    $associatedId[$targetClass->fieldNames[$targetColumn]] = $joinColumnValue;
                                }
                            }
                            if (!$associatedId) {
                                // Foreign key is NULL
                                $class->reflFields[$field]->setValue($entity, null);
                            } else {
                                $associatedEntity = $objectManager->getReference($targetClass->name, $associatedId);
                                $class->reflFields[$field]->setValue($entity, $associatedEntity);
                            }
                        } else {
                            // Inverse side of x-to-one can never be lazy
                            $class->reflFields[$field]->setValue($entity, $uow->getEntityPersister($assoc['targetEntity'])
                                ->loadOneToOneEntity($assoc, $entity));
                        }
                    } else {
                        $class->reflFields[$field]->setValue($entity, null);
                    }
                }
            } elseif ($assoc['type'] & ClassMetadata::ONE_TO_MANY) {
                if ($configuration->isClassAudited($assoc['targetEntity'])) {
                    if ($configuration->isLoadAuditedCollections()) {
                        $foreignKeys = array();
                        foreach ($targetClass->associationMappings[$assoc['mappedBy']]['sourceToTargetKeyColumns'] as $local => $foreign) {
                            $field = $class->getFieldForColumn($foreign);
                            $foreignKeys[$local] = $class->reflFields[$field]->getValue($entity);
                        }

                        $collection = new AuditedCollection($this, $targetClass->name, $targetClass, $assoc, $foreignKeys, $revision);

                        $class->reflFields[$assoc['fieldName']]->setValue($entity, $collection);
                    } else {
                        $class->reflFields[$assoc['fieldName']]->setValue($entity, new ArrayCollection());
                    }
                } else {
                    if ($configuration->isLoadNativeCollections()) {
                        $collection = new PersistentCollection($objectManager, $targetClass, new ArrayCollection());

                        $uow->getEntityPersister($assoc['targetEntity'])
                            ->loadOneToManyCollection($assoc, $entity, $collection);

                        $class->reflFields[$assoc['fieldName']]->setValue($entity, $collection);
                    } else {
                        $class->reflFields[$assoc['fieldName']]->setValue($entity, new ArrayCollection());
                    }
                }
            } else {
                // Inject collection
                $reflField = $class->reflFields[$field];
                $reflField->setValue($entity, new ArrayCollection());
            }
        }

        return $entity;
    }
}
