<?php

/*
 * (c) 2017 DreamCommerce
 *
 * @package DreamCommerce\Component\ObjectAudit
 * @author Michał Korus <michal.korus@dreamcommerce.com>
 * @link https://www.dreamcommerce.com
 *
 * (c) 2011 SimpleThings GmbH
 *
 * @package SimpleThings\EntityAudit
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 * @link http://www.simplethings.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use DreamCommerce\Bundle\ObjectAuditBundle\BaseObjectAuditManager;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotFoundException;
use DreamCommerce\Component\ObjectAudit\Model\AuditedCollection;
use DreamCommerce\Component\ObjectAudit\Model\ChangedObject;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use Exception;
use RuntimeException;

class ORMAuditManager extends BaseObjectAuditManager
{
    /**
     * Entity cache to prevent circular references.
     *
     * @var array
     */
    protected $entityCache = array();

    /**
     * {@inheritdoc}
     */
    public function findObjectByRevision(string $className, $objectId, RevisionInterface $revision, ObjectManager $objectManager = null, array $options = array())
    {
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();
        if (!$configuration->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        $options = array_merge(array('threatDeletionsAsExceptions' => false), $options);

        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        /** @var ClassMetadata $entityMetadata */
        $entityMetadata = $objectManager->getClassMetadata($className);
        /** @var EntityManagerInterface $auditObjectManager */
        $auditObjectManager = $this->getAuditObjectManager();
        $revisionMetadata = $auditObjectManager->getClassMetadata($this->revisionClass);
        $quoteStrategy = $auditObjectManager->getConfiguration()->getQuoteStrategy();
        $connection = $auditObjectManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $tableName = $this->getAuditTableNameForClass($className, $auditObjectManager);

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->from($tableName, 'e');
        $queryBuilder->setMaxResults(1);

        if (!is_array($objectId)) {
            $objectId = array($entityMetadata->identifier[0] => $objectId);
        }

        foreach ($revisionMetadata->identifier as $revisionIdField) {
            if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
            } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
            } else {
                continue;
            }
            $columnName = $this->getRevisionColumnName($configuration, $columnName);

            $queryBuilder->orderBy('e.'.$columnName, 'DESC');
            $queryBuilder->andWhere('e.'.$columnName.' <= :'.$columnName);
            $queryBuilder->addSelect('e.'.$columnName);
        }

        foreach ($entityMetadata->identifier as $idField) {
            if (isset($entityMetadata->fieldMappings[$idField])) {
                $columnName = $entityMetadata->fieldMappings[$idField]['columnName'];
            } elseif (isset($entityMetadata->associationMappings[$idField])) {
                $columnName = $entityMetadata->associationMappings[$idField]['joinColumns'][0];
            } else {
                continue;
            }

            $queryBuilder->andWhere('e.'.$columnName.' = :'.$columnName);
            $queryBuilder->addSelect('e.'.$columnName);
        }

        $columnMap = array();

        foreach ($entityMetadata->fieldNames as $columnName => $field) {
            $tableAlias = $entityMetadata->isInheritanceTypeJoined() && $entityMetadata->isInheritedField($field) && !$entityMetadata->isIdentifier($field)
                ? 're' // root entity
                : 'e';

            $queryBuilder->addSelect(sprintf(
                '%s.%s AS %s',
                $tableAlias,
                $quoteStrategy->getColumnName($field, $entityMetadata, $platform),
                $platform->quoteSingleIdentifier($field)
            ));
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

                $queryBuilder->addSelect($tableAlias.'.'.$sourceCol);
                $columnMap[$sourceCol] = $platform->getSQLResultCasing($sourceCol);
            }
        }

        if ($entityMetadata->isInheritanceTypeJoined() && $entityMetadata->name != $entityMetadata->rootEntityName) {
            /** @var ClassMetadata $rootClass */
            $rootClass = $objectManager->getClassMetadata($entityMetadata->rootEntityName);
            $rootTableName = $this->getAuditTableNameForClass($rootClass, $objectManager);
            $condition = array();

            foreach ($revisionMetadata->identifier as $revisionIdField) {
                if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                    $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
                } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                    $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
                } else {
                    continue;
                }
                $columnName = $this->getRevisionColumnName($configuration, $columnName);
                $condition[] = 're.'.$columnName.' = e.'.$columnName;
            }

            foreach ($entityMetadata->getIdentifierColumnNames() as $name) {
                $condition[] = 're.'.$name.' = e.'.$name;
            }

            $queryBuilder->innerJoin('e', $rootTableName, 're', implode(' AND ', $condition));
        }

        if (!$entityMetadata->isInheritanceTypeNone()) {
            $queryBuilder->addSelect($entityMetadata->discriminatorColumn['name']);

            if ($entityMetadata->isInheritanceTypeSingleTable()
                && $entityMetadata->discriminatorValue !== null) {

                // Support for single table inheritance sub-classes
                $allDiscrValues = array_flip($entityMetadata->discriminatorMap);
                $queriedDiscrValues = array($connection->quote($entityMetadata->discriminatorValue));
                foreach ($entityMetadata->subClasses as $subclassName) {
                    $queriedDiscrValues[] = $connection->quote($allDiscrValues[$subclassName]);
                }

                $queryBuilder->andWhere(sprintf(
                    '%s IN (%s)',
                    $entityMetadata->discriminatorColumn['name'],
                    implode(', ', $queriedDiscrValues)
                ));
            }
        }

        $revisionIds = $revisionMetadata->getIdentifierValues($revision);
        foreach ($revisionIds as $fieldName => $fieldValue) {
            $newFieldName = $this->getRevisionColumnName($configuration, $fieldName);
            $revisionIds[$newFieldName] = $fieldValue;
            unset($revisionIds[$fieldName]);
        }

        $queryBuilder->setParameters(array_merge($revisionIds, $objectId));
        $row = $queryBuilder->execute()->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw ObjectNotFoundException::forObjectAtSpecificRevision($entityMetadata->name, $objectId, $revision);
        }

        if ($options['threatDeletionsAsExceptions'] && $row[$configuration->getRevisionTypeFieldName()] == RevisionInterface::ACTION_DELETE) {
            throw ObjectDeletedException::forObjectAtSpecificRevision($entityMetadata->name, $objectId, $revision);
        }

        unset($row[$configuration->getRevisionTypeFieldName()]);

        return $this->createEntity($entityMetadata->name, $columnMap, $row, $revision, $revisionIds);
    }

    /**
     * {@inheritdoc}
     */
    public function findObjectsByFieldsAndRevision(string $className, array $fields, string $indexBy = null, RevisionInterface $revision, ObjectManager $objectManager = null, array $options = array()): array
    {
        if ($objectManager === null) {
            /** @var EntityManagerInterface $objectManager */
            $objectManager = $this->getDefaultObjectManager();
        }

        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();
        $auditClassMetadata = $objectManager->getClassMetadata($className);
        /** @var EntityManagerInterface $auditObjectManager */
        $auditObjectManager = $this->getAuditObjectManager();
        $revisionMetadata = $auditObjectManager->getClassMetadata($this->revisionClass);
        $identifierColumnNames = $auditClassMetadata->getIdentifierColumnNames();
        $connection = $objectManager->getConnection();

        /** @var EntityManagerInterface $auditManager */
        $auditObjectManager = $this->getAuditObjectManager();
        $tableName = $this->getAuditTableNameForClass($className, $auditObjectManager);

        $queryBuilder = $connection->createQueryBuilder()
            ->select($identifierColumnNames)
            ->from($tableName, 't');

        $revisionIdentifiers = $revisionMetadata->getIdentifierValues($revision);
        foreach ($revisionIdentifiers as $columnName => $columnValue) {
            $auditColumnName = $this->getRevisionColumnName($configuration, $columnName);

            $queryBuilder->select(sprintf('MAX(%1$s) as %1$s', $auditColumnName));
            $queryBuilder->where(sprintf(
                '%s <= %s',
                $auditColumnName,
                $queryBuilder->createPositionalParameter($columnValue)
            ));

            $revisionIdentifiers[$auditColumnName] = $columnValue;
            unset($revisionIdentifiers[$columnName]);
        }

        if ($indexBy !== null) {
            $queryBuilder->addSelect($indexBy);
        }

        foreach ($fields as $column => $value) {
            $queryBuilder->andWhere($column . ' = ' . $queryBuilder->createPositionalParameter($value));
        }

        //we check for revisions greater than current belonging to other entities
        $belongingToEntitiesQB = $this->createBelongingToOtherEntitiesQueryBuilder(
            $tableName, $fields, $revisionIdentifiers, $identifierColumnNames, $connection, $queryBuilder
        );
        $queryBuilder->andWhere(sprintf('NOT EXISTS(%s)', $belongingToEntitiesQB->getSQL()));

        //check for deleted revisions older than requested
        $deletedRevisionQB = $this->createDeletedRevisionsQueryBuilder(
            $tableName, $revisionIdentifiers, $identifierColumnNames, $connection, $queryBuilder
        );
        $queryBuilder->andWhere(sprintf('NOT EXISTS(%s)', $deletedRevisionQB->getSQL()));

        $queryBuilder->andWhere(sprintf(
            '%s <> %s',
            $configuration->getRevisionTypeFieldName(),
            $queryBuilder->createPositionalParameter(RevisionInterface::ACTION_DELETE)
        ));

        $groupBy = $identifierColumnNames;
        if ($indexBy != null) {
            $groupBy[] = $indexBy;
        }

        $queryBuilder->groupBy($groupBy);

        foreach ($identifierColumnNames as $identifierColumnName) {
            $queryBuilder->addOrderBy($identifierColumnName, 'ASC');
        }

        $result = $queryBuilder->execute()->fetchAll();

        $entities = array();
        foreach ($result as $identifiers) {
            $entity = $this->findObjectByRevision($className, $identifiers, $revision, $objectManager, $options);

            if ($indexBy !== null) {
                $key = $result[$indexBy];
                $entities[$key] = $entity;
            } else {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function findObjectsChangedAtRevision(string $className, RevisionInterface $revision, ObjectManager $objectManager = null, array $options = array()): array
    {
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();
        if (!$configuration->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        /** @var EntityManagerInterface $objectManager */
        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        $changedEntities = array();
        /** @var ClassMetadataInfo|ClassMetadata $class */
        $class = $objectManager->getClassMetadata($className);

        if ($class->isInheritanceTypeSingleTable() && count($class->subClasses) > 0) {
            return array();
        }

        $connection = $objectManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $quoteStrategy = $objectManager->getConfiguration()->getQuoteStrategy();
        /** @var EntityManagerInterface $auditObjectManager */
        $auditObjectManager = $this->getAuditObjectManager();
        $revisionMetadata = $auditObjectManager->getClassMetadata($this->revisionClass);

        $tableName = $this->getAuditTableNameForClass($className);
        $revisionIds = $revisionMetadata->getIdentifierValues($revision);

        $bindValues = array();

        foreach ($revisionIds as $fieldName => $fieldValue) {
            $fieldName = $this->getRevisionColumnName($configuration, $fieldName);
            $bindValues[$fieldName] = $fieldValue;
        }

        $queryBuilder = $connection->createQueryBuilder()
            ->select('e.'.$configuration->getRevisionTypeFieldName())
            ->from($tableName, 'e');

        foreach ($revisionMetadata->identifier as $revisionIdField) {
            if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
            } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
            } else {
                continue;
            }
            $columnName = $this->getRevisionColumnName($configuration, $columnName);
            $queryBuilder->andWhere('e.'.$columnName.' = :'.$columnName);
        }

        $columnMap = array();

        foreach ($class->fieldNames as $columnName => $field) {
            $tableAlias = $class->isInheritanceTypeJoined() && $class->isInheritedField($field) && !$class->isIdentifier($field)
                ? 're' // root entity
                : 'e';

            $queryBuilder->addSelect(sprintf(
                '%s.%s AS %s',
                $tableAlias,
                $quoteStrategy->getColumnName($field, $class, $platform),
                $platform->quoteSingleIdentifier($field)
            ));
            $columnMap[$field] = $platform->getSQLResultCasing($columnName);
        }

        foreach ($class->associationMappings as $assoc) {
            if (($assoc['type'] & ClassMetadata::TO_ONE) > 0 && $assoc['isOwningSide']) {
                foreach ($assoc['targetToSourceKeyColumns'] as $sourceCol) {
                    $queryBuilder->addSelect($sourceCol);
                    $columnMap[$sourceCol] = $platform->getSQLResultCasing($sourceCol);
                }
            }
        }

        if ($class->isInheritanceTypeSingleTable()) {
            $columnName = $class->discriminatorColumn['fieldName'];
            $bindValues[$columnName] = $class->discriminatorValue;
            $queryBuilder->addSelect('e.'.$class->discriminatorColumn['name']);
            $queryBuilder->andWhere('e.'.$columnName.' = :'.$columnName);
        } elseif ($class->isInheritanceTypeJoined() && $class->rootEntityName != $class->name) {
            $columnList[] = 're.'.$class->discriminatorColumn['name'];

            /** @var ClassMetadataInfo $rootClass */
            $rootClass = $objectManager->getClassMetadata($class->rootEntityName);
            $rootTableName = $this->getAuditTableNameForClass($rootClass, $objectManager);

            $conditions = array();
            foreach ($revisionMetadata->identifier as $revisionIdField) {
                if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                    $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
                } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                    $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
                } else {
                    continue;
                }
                $columnName = $this->getRevisionColumnName($configuration, $columnName);
                $conditions[] = 're.'.$columnName.' = e.'.$columnName;
            }

            foreach ($class->getIdentifierColumnNames() as $name) {
                $conditions[] = 're.'.$name.' = e.'.$name;
            }

            $queryBuilder->addSelect('re.'.$class->discriminatorColumn['name']);
            $queryBuilder->innerJoin('e', $rootTableName, 're', implode(' AND ', $conditions));
        }

        $queryBuilder->setParameters($bindValues);
        $revisionsData = $queryBuilder->execute()->fetchAll();

        $revisionFieldType = $configuration->getRevisionTypeFieldType();
        $revisionFieldName = $configuration->getRevisionTypeFieldName();
        $revisionType = null;
        if (Type::hasType($revisionFieldType)) {
            $revisionType = Type::getType($revisionFieldType);
        }

        foreach ($revisionsData as $row) {
            $id = array();

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
            unset($row[$revisionFieldName]);

            if ($revisionType !== null) {
                $objectRevType = $revisionType->convertToPHPValue($objectRevType, $platform);
            }

            $changedEntities[] = new ChangedObject(
                $entity,
                $revision,
                $objectManager,
                $row,
                $objectRevType
            );
        }

        return $changedEntities;
    }

    /**
     * {@inheritdoc}
     */
    public function findObjectRevisions(string $className, $objectIds, ObjectManager $objectManager = null): Collection
    {
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();
        if (!$configuration->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        /** @var EntityManagerInterface $objectManager */
        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }

        /** @var EntityManagerInterface $auditManager */
        $auditManager = $this->getAuditObjectManager();
        $revisionMetadata = $auditManager->getClassMetadata($this->revisionClass);
        $revisionTableName = $revisionMetadata->getTableName();

        /** @var ClassMetadata $entityMetadata */
        $entityMetadata = $objectManager->getClassMetadata($className);
        $entityTableName = $this->getAuditTableNameForClass($className, $objectManager);

        if (!is_array($objectIds)) {
            $objectIds = array($entityMetadata->identifier[0] => $objectIds);
        }

        $connection = $objectManager->getConnection();
        $queryBuilder = $connection->createQueryBuilder()
            ->select('r.*')
            ->from($revisionTableName, 'r');

        foreach ($entityMetadata->identifier as $entityIdField) {
            if (isset($entityMetadata->fieldMappings[$entityIdField])) {
                $columnName = $entityMetadata->fieldMappings[$entityIdField]['columnName'];
            } elseif (isset($entityMetadata->associationMappings[$entityIdField])) {
                $columnName = $entityMetadata->associationMappings[$entityIdField]['joinColumns'][0];
            } else {
                continue;
            }
            $queryBuilder->andWhere('e.'.$columnName.' = :'.$columnName);
        }

        $conditions = array();
        foreach ($revisionMetadata->identifier as $revisionIdField) {
            if (isset($revisionMetadata->fieldMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
            } elseif (isset($revisionMetadata->associationMappings[$revisionIdField])) {
                $columnName = $revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0];
            } else {
                continue;
            }
            $conditions[] = 'r.'.$revisionIdField.' = e.'.$this->getRevisionColumnName($configuration, $columnName);
            $queryBuilder->orderBy('r.'.$columnName, 'DESC');
        }
        $queryBuilder->innerJoin('r', $entityTableName, 'e', implode(' AND ', $conditions));

        $rsm = new ResultSetMappingBuilder($auditManager);
        $rsm->addRootEntityFromClassMetadata($this->revisionClass, 'r');
        $query = $auditManager->createNativeQuery($queryBuilder->getSQL(), $rsm);
        $query->setParameters($objectIds);

        $result = $query->getResult();

        return new ArrayCollection($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectHistory(string $className, $objectId, ObjectManager $objectManager = null, array $options = array()): array
    {
        // TODO
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
    public function saveObjectRevisionData(ChangedObject $changedObject)
    {
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
     * {@inheritdoc}
     */
    public function clearObjectCache()
    {
        $this->entityCache = array();
    }

    /**
     * @param string        $className
     * @param mixed         $objectIds
     * @param ObjectManager $objectManager
     * @param string        $sort
     *
     * @throws ObjectNotAuditedException
     * @throws ObjectNotFoundException
     *
     * @return null|object
     */
    private function getObjectRevision(string $className, $objectIds, ObjectManager $objectManager, $sort = 'ASC')
    {
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();
        if (!$configuration->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        /** @var ClassMetadataInfo $entityMetadata */
        $entityMetadata = $objectManager->getClassMetadata($className);
        $entityTableName = $this->getAuditTableNameForClass($className, $objectManager);
        /** @var EntityManagerInterface $auditEntityManager */
        $auditEntityManager = $this->getAuditObjectManager();
        $revisionMetadata = $auditEntityManager->getClassMetadata($this->revisionClass);
        $connection = $auditEntityManager->getConnection();

        if (!is_array($objectIds)) {
            $objectIds = array($entityMetadata->identifier[0] => $objectIds);
        }

        $queryBuilder = $connection->createQueryBuilder()
            ->setMaxResults(1)
            ->from($entityTableName, 'e');

        foreach ($entityMetadata->identifier as $idField) {
            if (isset($entityMetadata->fieldMappings[$idField])) {
                $columnName = $entityMetadata->fieldMappings[$idField]['columnName'];
            } elseif (isset($entityMetadata->associationMappings[$idField])) {
                $columnName = $entityMetadata->associationMappings[$idField]['joinColumns'][0];
            } else {
                continue;
            }

            $queryBuilder->andWhere($columnName.' = :'.$columnName);
        }

        $revisionIdMap = array();
        foreach ($revisionMetadata->identifier as $idField) {
            $revisionIdName = $this->getRevisionColumnName($configuration, $idField);
            $revisionIdMap[$revisionIdName] = $idField;
            $queryBuilder->select($revisionIdName);
            $queryBuilder->orderBy($revisionIdName, $sort);
        }

        $queryBuilder->setParameters($objectIds);
        $revisionIds = $queryBuilder->execute()->fetch(\PDO::FETCH_ASSOC);

        if ($revisionIds === false) {
            throw ObjectNotFoundException::forObjectIdentifiers($className, $objectIds);
        }

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
     * @throws ObjectDeletedException
     * @throws ObjectNotFoundException
     * @throws ObjectNotAuditedException
     * @throws DBALException
     * @throws MappingException
     * @throws ORMException
     * @throws Exception
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
            $this->entityCache[$className] = array();

            if (!isset($this->entityCache[$className][$key])) {
                $this->entityCache[$className][$key] = array();
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
                        // Primary Key. Used for audit tables queries.
                        $pk = array();
                        // Primary Field. Used when fallback to Doctrine finder.
                        $pf = array();

                        if ($assoc['isOwningSide']) {
                            foreach ($assoc['targetToSourceKeyColumns'] as $foreign => $local) {
                                $pk[$foreign] = $pf[$foreign] = $data[$columnMap[$local]];
                            }
                        } else {
                            /** @var ClassMetadata $otherEntityMeta */
                            $otherEntityAssoc = $objectManager->getClassMetadata($assoc['targetEntity'])->associationMappings[$assoc['mappedBy']];

                            foreach ($otherEntityAssoc['targetToSourceKeyColumns'] as $local => $foreign) {
                                $pk[$foreign] = $pf[$otherEntityAssoc['fieldName']] = $data[$class->getFieldName($local)];
                            }
                        }

                        $pk = array_filter($pk, function ($value) {
                            return !is_null($value);
                        });

                        if (!$pk) {
                            $class->reflFields[$field]->setValue($entity, null);
                        } else {
                            try {
                                $value = $this->findObjectByRevision($targetClass->name, $pk, $revision, $objectManager, array('threatDeletionsAsExceptions' => true));
                            } catch (ObjectDeletedException $e) {
                                $value = null;
                            } catch (ObjectNotFoundException $e) {
                                // The entity does not have any revision yet. So let's get the actual state of it.
                                $value = $objectManager->getRepository($targetClass->name)->findOneBy($pf);
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

                        $indexBy = null;
                        if (isset($assoc['indexBy'])) {
                            $indexBy = $assoc['indexBy'];
                        }

                        $collection = new AuditedCollection($targetClass->name, $foreignKeys, $indexBy, $revision, $objectManager, $this);

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

    /**
     * @param ORMAuditConfiguration $configuration
     * @param string                $columnName
     *
     * @return string
     */
    private function getRevisionColumnName(ORMAuditConfiguration $configuration, string $columnName): string
    {
        return $configuration->getRevisionIdFieldPrefix().$columnName.$configuration->getRevisionIdFieldSuffix();
    }

    /**
     * @param string $tableName
     * @param array $foreignKeys
     * @param array $revisionIdentifiers
     * @param array $identifierColumnNames
     * @param Connection $connection
     * @param QueryBuilder $parentQueryBuilder
     * @return QueryBuilder
     */
    private function createBelongingToOtherEntitiesQueryBuilder(
        string $tableName, array $foreignKeys, array $revisionIdentifiers,
        array $identifierColumnNames, Connection $connection,
        QueryBuilder $parentQueryBuilder): QueryBuilder
    {
        $queryBuilder = $connection->createQueryBuilder()
            ->select('1')
            ->from($tableName, 'st');

        foreach ($revisionIdentifiers as $columnName => $columnValue) {
            $queryBuilder->andWhere(sprintf('st.%1$s > t.%1$s', $columnName));

            $queryBuilder->andWhere(sprintf(
                'st.%s <= %s',
                $columnName,
                $parentQueryBuilder->createPositionalParameter($columnValue)
            ));
        }

        //ids
        foreach ($identifierColumnNames as $name) {
            $queryBuilder->andWhere(sprintf('st.%1$s = t.%1$s', $name));
        }

        //master entity query, not equals
        $notEqualParts = $nullParts = array();
        foreach ($foreignKeys as $column => $value) {
            $notEqualParts[] = $column . ' <> ' . $parentQueryBuilder->createPositionalParameter($value);
            $nullParts[] = $column . ' IS NULL';
        }

        $expr = $queryBuilder->expr();
        $queryBuilder->andWhere(
            $expr->orX(
                $expr->andX(...$notEqualParts),
                $expr->andX(...$nullParts)
            )
        );

        return $queryBuilder;
    }

    /**
     * @param string $tableName
     * @param array $revisionIdentifiers
     * @param array $identifierColumnNames
     * @param Connection $connection
     * @param QueryBuilder $parentQueryBuilder
     * @return QueryBuilder
     */
    private function createDeletedRevisionsQueryBuilder(
        string $tableName, array $revisionIdentifiers,
        array $identifierColumnNames, Connection $connection,
        QueryBuilder $parentQueryBuilder): QueryBuilder
    {
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();

        $queryBuilder = $connection->createQueryBuilder()
            ->select('1')
            ->from($tableName, 'sd');

        foreach ($revisionIdentifiers as $columnName => $columnValue) {
            $queryBuilder->andWhere(sprintf('sd.%1$s > t.%1$s', $columnName));
            $queryBuilder->andWhere(sprintf(
                'sd.%s <= %s',
                $columnName,
                $parentQueryBuilder->createPositionalParameter($columnValue)
            ));
        }

        $queryBuilder->andWhere(sprintf(
            'sd.%s = %s',
            $configuration->getRevisionTypeFieldName(),
            $parentQueryBuilder->createPositionalParameter(RevisionInterface::ACTION_DELETE)
        ));

        //ids
        foreach ($identifierColumnNames as $name) {
            $queryBuilder->andWhere(sprintf('sd.%1$s = t.%1$s', $name));
        }

        return $queryBuilder;
    }
}
