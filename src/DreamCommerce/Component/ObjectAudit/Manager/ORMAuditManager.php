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

namespace DreamCommerce\Component\ObjectAudit\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\QuoteStrategy;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use DreamCommerce\Component\ObjectAudit\Configuration\ORMAuditConfiguration;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectAuditDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectAuditNotFoundException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Factory\ObjectAuditFactoryInterface;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadataFactory;
use DreamCommerce\Component\ObjectAudit\Model\ObjectAudit;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;

class ORMAuditManager extends BaseObjectAuditManager
{
    /**
     * @var array
     */
    protected $insertRevisionSQL = array();

    /**
     * @var ClassMetadata
     */
    protected $revisionMetadata;

    /**
     * @var EntityManagerInterface
     */
    protected $auditPersistManager;

    /**
     * @var QuoteStrategy
     */
    protected $auditQuoteStrategy;

    /**
     * @var Connection
     */
    protected $auditConnection;

    /**
     * @var AbstractPlatform
     */
    protected $auditPlatform;

    /**
     * @var array|null
     */
    protected $auditRevisionColumns;

    /**
     * {@inheritdoc}
     */
    public function __construct(ORMAuditConfiguration $configuration,
                                EntityManagerInterface $persistManager,
                                EntityManagerInterface $auditPersistManager,
                                RevisionManagerInterface $revisionManager,
                                ObjectAuditFactoryInterface $objectAuditFactory,
                                ObjectAuditMetadataFactory $objectAuditMetadataFactory
    ) {
        parent::__construct(
            $configuration, $persistManager, $auditPersistManager,
            $revisionManager, $objectAuditFactory, $objectAuditMetadataFactory
        );

        $this->revisionMetadata = $this->revisionManager->getRevisionMetadata();
        $this->auditQuoteStrategy = $this->auditPersistManager->getConfiguration()->getQuoteStrategy();
        $this->auditConnection = $this->auditPersistManager->getConnection();
        $this->auditPlatform = $this->auditConnection->getDatabasePlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function findObjectByRevision(string $className, $objectIds, RevisionInterface $revision, array $options = array())
    {
        $className = ClassUtils::getRealClass($className);

        if (!$this->objectAuditMetadataFactory->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        $options = array_merge(array('threatDeletionsAsExceptions' => false), $options);

        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->persistManager->getClassMetadata($className);
        $tableName = $this->getAuditTableNameForClass($className);

        $queryBuilder = $this->auditConnection->createQueryBuilder();
        $queryBuilder->from($tableName, 'e');
        $queryBuilder->setMaxResults(1);

        if (!is_array($objectIds)) {
            if (count($classMetadata->identifier) > 1) {
                throw new \Exception(); // TODO
            }
            $entityIdentifiers = $classMetadata->getIdentifierColumnNames();
            $objectIds = array($entityIdentifiers[0] => $objectIds);
        }

        $revisionColumns = $this->getAuditRevisionColumns();
        foreach ($revisionColumns as $revisionColumn) {
            if (isset($objectIds[$revisionColumn])) {
                unset($objectIds[$revisionColumn]);
            }
        }

        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->configuration;
        $fieldRevisionTypeName = $configuration->getRevisionTypeFieldName();
        $queryBuilder->addSelect('e.'.$fieldRevisionTypeName);

        if (count($classMetadata->identifier) !== count($objectIds)) {
            throw new \Exception(); // TODO
        } else {
            $entityIdentifiers = $classMetadata->getIdentifierColumnNames();
            if (!empty(array_diff($entityIdentifiers, array_keys($objectIds)))) {
                throw new \Exception(); // TODO
            }
        }

        foreach ($revisionColumns as $revisionColumn) {
            $queryBuilder->andWhere('e.'.$revisionColumn.' <= :'.$revisionColumn);
            $queryBuilder->addSelect('e.'.$revisionColumn);
            $queryBuilder->orderBy('e.'.$revisionColumn, 'DESC');
        }

        foreach ($classMetadata->identifier as $idField) {
            if (isset($classMetadata->fieldMappings[$idField])) {
                $columnName = $classMetadata->fieldMappings[$idField]['columnName'];
            } elseif (isset($classMetadata->associationMappings[$idField])) {
                $columnName = $classMetadata->associationMappings[$idField]['joinColumns'][0]['name'];
            } else {
                continue;
            }

            $queryBuilder->andWhere('e.'.$columnName.' = :'.$columnName);
            $queryBuilder->addSelect('e.'.$columnName);
        }

        $columnMap = array();

        foreach ($classMetadata->fieldNames as $columnName => $field) {
            $tableAlias = $classMetadata->isInheritanceTypeJoined() && $classMetadata->isInheritedField($field) && !$classMetadata->isIdentifier($field)
                ? 're' // root entity
                : 'e';

            $queryBuilder->addSelect(sprintf(
                '%s.%s AS %s',
                $tableAlias,
                $this->auditQuoteStrategy->getColumnName($field, $classMetadata, $this->auditPlatform),
                $this->auditPlatform->quoteSingleIdentifier($field)
            ));
            $columnMap[$field] = $this->auditPlatform->getSQLResultCasing($columnName);
        }

        foreach ($classMetadata->associationMappings as $assoc) {
            if (($assoc['type'] & ClassMetadata::TO_ONE) == 0 || !$assoc['isOwningSide']) {
                continue;
            }

            foreach ($assoc['joinColumnFieldNames'] as $sourceCol) {
                $tableAlias = $classMetadata->isInheritanceTypeJoined() && $classMetadata->isInheritedAssociation($assoc['fieldName']) && !$classMetadata->isIdentifier($assoc['fieldName'])
                    ? 're' // root entity
                    : 'e';

                $queryBuilder->addSelect($tableAlias.'.'.$sourceCol);
                $columnMap[$sourceCol] = $this->auditPlatform->getSQLResultCasing($sourceCol);
            }
        }

        if ($classMetadata->isInheritanceTypeJoined() && $classMetadata->name != $classMetadata->rootEntityName) {
            /** @var ClassMetadata $rootClass */
            $rootClass = $this->persistManager->getClassMetadata($classMetadata->rootEntityName);
            $rootTableName = $this->getAuditTableNameForClass($rootClass->name);
            $condition = array();

            foreach ($revisionColumns as $revisionColumn) {
                $condition[] = 're.'.$revisionColumn.' = e.'.$revisionColumn;
            }

            foreach ($classMetadata->getIdentifierColumnNames() as $name) {
                $condition[] = 're.'.$name.' = e.'.$name;
            }

            $queryBuilder->innerJoin('e', $rootTableName, 're', implode(' AND ', $condition));
        }

        if (!$classMetadata->isInheritanceTypeNone()) {
            $queryBuilder->addSelect($classMetadata->discriminatorColumn['name']);

            if ($classMetadata->isInheritanceTypeSingleTable()
                && $classMetadata->discriminatorValue !== null) {

                // Support for single table inheritance sub-classes
                $allDiscrValues = array_flip($classMetadata->discriminatorMap);
                $queriedDiscrValues = array($this->auditConnection->quote($classMetadata->discriminatorValue));
                foreach ($classMetadata->subClasses as $subclassName) {
                    $queriedDiscrValues[] = $this->auditConnection->quote($allDiscrValues[$subclassName]);
                }

                $queryBuilder->andWhere(sprintf(
                    '%s IN (%s)',
                    $classMetadata->discriminatorColumn['name'],
                    implode(', ', $queriedDiscrValues)
                ));
            }
        }

        $revisionIds = $this->revisionMetadata->getIdentifierValues($revision);
        foreach ($revisionIds as $fieldName => $fieldValue) {
            $newFieldName = $this->getRevisionColumnName($fieldName);
            $revisionIds[$newFieldName] = $fieldValue;
            unset($revisionIds[$fieldName]);
        }

        $queryBuilder->setParameters(array_merge($revisionIds, $objectIds));
        $row = $queryBuilder->execute()->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw ObjectAuditNotFoundException::forObjectAtSpecificRevision($classMetadata->name, $objectIds, $revision);
        }

        $revisionType = Type::getType($configuration->getRevisionTypeFieldType());
        $revisionTypeValue = $revisionType->convertToPHPValue($row[$fieldRevisionTypeName], $this->auditPlatform);

        if ($options['threatDeletionsAsExceptions'] && $revisionTypeValue == RevisionInterface::ACTION_DELETE) {
            throw ObjectAuditDeletedException::forObjectAtSpecificRevision($classMetadata->name, $objectIds, $revision);
        }

        unset($row[$fieldRevisionTypeName]);

        return $this->objectAuditFactory->createNewAudit($classMetadata->name, $columnMap, $row, $revision, $this);
    }

    /**
     * {@inheritdoc}
     */
    public function findObjectsByFieldsAndRevision(string $className, array $fields, string $indexBy = null, RevisionInterface $revision, array $options = array()): array
    {
        $className = ClassUtils::getRealClass($className);

        if (!$this->objectAuditMetadataFactory->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->configuration;
        /** @var EntityManagerInterface $persistManager */
        $persistManager = $this->persistManager;

        $classMetadata = $persistManager->getClassMetadata($className);
        $identifierColumnNames = $classMetadata->getIdentifierColumnNames();
        $tableName = $this->getAuditTableNameForClass($className);

        $queryBuilder = $this->auditConnection->createQueryBuilder()
            ->select($identifierColumnNames)
            ->from($tableName, 't');

        $revisionIdentifiers = $this->revisionMetadata->getIdentifierValues($revision);
        foreach ($revisionIdentifiers as $columnName => $columnValue) {
            $auditColumnName = $this->getRevisionColumnName($columnName);

            $queryBuilder->addSelect(sprintf('MAX(%1$s) as %1$s', $auditColumnName));
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
            $queryBuilder->andWhere($column.' = '.$queryBuilder->createPositionalParameter($value));
        }

        //we check for revisions greater than current belonging to other entities
        $belongingToEntitiesQB = $this->createBelongingToOtherEntitiesQueryBuilder(
            $tableName, $fields, $revisionIdentifiers, $identifierColumnNames, $this->auditConnection, $queryBuilder
        );
        $queryBuilder->andWhere(sprintf('NOT EXISTS(%s)', $belongingToEntitiesQB->getSQL()));

        //check for deleted revisions older than requested
        $deletedRevisionQB = $this->createDeletedRevisionsQueryBuilder(
            $tableName, $revisionIdentifiers, $identifierColumnNames, $this->auditConnection, $queryBuilder
        );
        $queryBuilder->andWhere(sprintf('NOT EXISTS(%s)', $deletedRevisionQB->getSQL()));

        $type = Type::getType($configuration->getRevisionTypeFieldType());
        $queryBuilder->andWhere(sprintf(
            '%s <> %s',
            $configuration->getRevisionTypeFieldName(),
            $queryBuilder->createPositionalParameter(
                $type->convertToDatabaseValue(RevisionInterface::ACTION_DELETE, $this->auditPlatform)
            )
        ));

        $groupBy = $identifierColumnNames;
        if ($indexBy != null) {
            $groupBy[] = $indexBy;
        }

        $queryBuilder->groupBy($groupBy);

        foreach ($identifierColumnNames as $identifierColumnName) {
            $queryBuilder->addOrderBy($identifierColumnName, 'ASC');
        }

        $discriminatorColumn = null;
        if(!$classMetadata->isInheritanceTypeNone()) {
            $discriminatorColumn = $classMetadata->discriminatorColumn['name'];
            $queryBuilder->addSelect($discriminatorColumn);
        }

        $result = $queryBuilder->execute()->fetchAll();

        $proxyFactory = new LazyLoadingValueHolderFactory();
        $entities = array();

        foreach ($result as $identifiers) {
            $key = null;
            if ($indexBy !== null) {
                $key = $identifiers[$indexBy];
                unset($identifiers[$indexBy]);
            }

            $proxyClassName = $className;
            if(!$classMetadata->isInheritanceTypeNone()) {
                $discriminator = $identifiers[$discriminatorColumn];
                unset($identifiers[$discriminatorColumn]);
                $proxyClassName = $classMetadata->discriminatorMap[$discriminator];
            }

            $proxy = $proxyFactory->createProxy(
                $proxyClassName,
                function (& $wrappedObject, $proxy, $method, $parameters, & $initializer) use($className, $identifiers, $revision, $options) {
                    $wrappedObject = $this->findObjectByRevision($className, $identifiers, $revision, $options);
                    $initializer = null;
                }
            );

            if ($key !== null) {
                $entities[$key] = $proxy;
            } else {
                $entities[] = $proxy;
            }
        }

        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function findObjectsChangedAtRevision(string $className, RevisionInterface $revision, array $options = array()): array
    {
        $className = ClassUtils::getRealClass($className);

        if (!$this->objectAuditMetadataFactory->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->configuration;
        /** @var ClassMetadataInfo|ClassMetadata $classMetadata */
        $classMetadata = $this->persistManager->getClassMetadata($className);

        if ($classMetadata->isInheritanceTypeSingleTable() && count($classMetadata->subClasses) > 0) {
            return array();
        }

        $tableName = $this->getAuditTableNameForClass($className);
        $revisionIds = $this->revisionMetadata->getIdentifierValues($revision);
        $revisionColumns = $this->getAuditRevisionColumns();
        $bindValues = array();
        $columnMap = array();

        $queryBuilder = $this->auditConnection->createQueryBuilder()
            ->select('e.'.$configuration->getRevisionTypeFieldName())
            ->from($tableName, 'e');

        foreach ($revisionIds as $fieldName => $fieldValue) {
            $fieldName = $this->getRevisionColumnName($fieldName);
            $bindValues[$fieldName] = $fieldValue;
        }

        foreach ($revisionColumns as $revisionColumn) {
            $queryBuilder->andWhere('e.'.$revisionColumn.' = :'.$revisionColumn);
        }

        foreach ($classMetadata->fieldNames as $columnName => $field) {
            $tableAlias = $classMetadata->isInheritanceTypeJoined() && $classMetadata->isInheritedField($field) && !$classMetadata->isIdentifier($field)
                ? 're' // root entity
                : 'e';

            $queryBuilder->addSelect(sprintf(
                '%s.%s AS %s',
                $tableAlias,
                $this->auditQuoteStrategy->getColumnName($field, $classMetadata, $this->auditPlatform),
                $this->auditPlatform->quoteSingleIdentifier($field)
            ));
            $columnMap[$field] = $this->auditPlatform->getSQLResultCasing($columnName);
        }

        foreach ($classMetadata->associationMappings as $assoc) {
            if (($assoc['type'] & ClassMetadata::TO_ONE) > 0 && $assoc['isOwningSide']) {
                foreach ($assoc['targetToSourceKeyColumns'] as $sourceCol) {
                    $queryBuilder->addSelect($sourceCol);
                    $columnMap[$sourceCol] = $this->auditPlatform->getSQLResultCasing($sourceCol);
                }
            }
        }

        if ($classMetadata->isInheritanceTypeSingleTable()) {
            $columnName = $classMetadata->discriminatorColumn['fieldName'];
            $bindValues[$columnName] = $classMetadata->discriminatorValue;
            $queryBuilder->addSelect('e.'.$classMetadata->discriminatorColumn['name']);
            $queryBuilder->andWhere('e.'.$columnName.' = :'.$columnName);
        } elseif ($classMetadata->isInheritanceTypeJoined() && $classMetadata->rootEntityName != $classMetadata->name) {
            $columnList[] = 're.'.$classMetadata->discriminatorColumn['name'];

            /** @var ClassMetadata $rootClass */
            $rootClass = $this->persistManager->getClassMetadata($classMetadata->rootEntityName);
            $rootTableName = $this->getAuditTableNameForClass($rootClass->name);

            $conditions = array();
            foreach ($revisionColumns as $revisionColumn) {
                $conditions[] = 're.'.$revisionColumn.' = e.'.$revisionColumn;
            }

            foreach ($classMetadata->getIdentifierColumnNames() as $name) {
                $conditions[] = 're.'.$name.' = e.'.$name;
            }

            $queryBuilder->addSelect('re.'.$classMetadata->discriminatorColumn['name']);
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

        $objects = array();
        foreach ($revisionsData as $row) {
            $identifiers = array();

            foreach ($classMetadata->identifier as $idField) {
                $identifiers[$idField] = $row[$idField];
            }

            $entity = $this->objectAuditFactory->createNewAudit(
                $className,
                $columnMap,
                $row,
                $revision,
                $this
            );

            $objectRevType = $row[$revisionFieldName];
            unset($row[$revisionFieldName]);

            if ($revisionType !== null) {
                $objectRevType = $revisionType->convertToPHPValue($objectRevType, $this->auditPlatform);
            }

            $objects[] = new ObjectAudit(
                $entity,
                $className,
                $identifiers,
                $revision,
                $this->persistManager,
                $row,
                $objectRevType
            );
        }

        return $objects;
    }

    /**
     * {@inheritdoc}
     */
    public function findObjectRevisions(string $className, $objectIds): Collection
    {
        $className = ClassUtils::getRealClass($className);

        if (!$this->objectAuditMetadataFactory->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        /** @var EntityManagerInterface $persistManager */
        $persistManager = $this->persistManager;
        $revisionTableName = $this->revisionMetadata->getTableName();

        /** @var ClassMetadata $classMetadata */
        $classMetadata = $persistManager->getClassMetadata($className);
        $entityTableName = $this->getAuditTableNameForClass($className);

        if (!is_array($objectIds)) {
            $entityIdentifiers = $classMetadata->getIdentifierColumnNames();
            $objectIds = array($entityIdentifiers[0] => $objectIds);
        }

        $queryBuilder = $this->auditConnection->createQueryBuilder()
            ->select('r.*')
            ->from($revisionTableName, 'r');

        $entityColumns = $classMetadata->getIdentifierColumnNames();
        foreach ($entityColumns as $entityColumn) {
            $queryBuilder->andWhere('e.'.$entityColumn.' = :'.$entityColumn);
        }

        $conditions = array();
        foreach ($this->revisionMetadata->identifier as $revisionIdField) {
            if (isset($this->revisionMetadata->fieldMappings[$revisionIdField])) {
                $columnName = $this->revisionMetadata->fieldMappings[$revisionIdField]['columnName'];
            } elseif (isset($this->revisionMetadata->associationMappings[$revisionIdField])) {
                $columnName = $this->revisionMetadata->associationMappings[$revisionIdField]['joinColumns'][0]['name'];
            } else {
                continue;
            }
            $conditions[] = 'r.'.$revisionIdField.' = e.'.$this->getRevisionColumnName($columnName);
            $queryBuilder->orderBy('r.'.$columnName, 'DESC');
        }
        $queryBuilder->innerJoin('r', $entityTableName, 'e', implode(' AND ', $conditions));

        $rsm = new ResultSetMappingBuilder($this->auditPersistManager);
        $rsm->addRootEntityFromClassMetadata($this->revisionManager->getRevisionClassName(), 'r');
        $query = $this->auditPersistManager->createNativeQuery($queryBuilder->getSQL(), $rsm);
        $query->setParameters($objectIds);

        $result = $query->getResult();

        return new ArrayCollection($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectHistory(string $className, $objectIds, array $options = array()): array
    {
        $className = ClassUtils::getRealClass($className);

        if (!$this->objectAuditMetadataFactory->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->configuration;
        /** @var EntityManagerInterface $persistManager */
        $persistManager = $this->persistManager;

        $tableName = $this->getAuditTableNameForClass($className);
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $persistManager->getClassMetadata($className);
        $revisionIdentifierNames = $this->revisionMetadata->getIdentifierColumnNames();
        $revisionFieldType = $configuration->getRevisionTypeFieldType();
        $revisionFieldName = $configuration->getRevisionTypeFieldName();
        $revisionType = null;

        if (Type::hasType($revisionFieldType)) {
            $revisionType = Type::getType($revisionFieldType);
        }

        $queryBuilder = $this->auditConnection->createQueryBuilder()
            ->select($revisionFieldName)
            ->from($tableName, 'e');

        foreach ($revisionIdentifierNames as $revisionIdentifierName) {
            $revisionIdentifierName = $this->getRevisionColumnName($revisionIdentifierName);
            $queryBuilder->addSelect($revisionIdentifierName);
            $queryBuilder->orderBy($revisionIdentifierName, 'DESC');
        }

        if (!is_array($objectIds)) {
            $entityIdentifiers = $classMetadata->getIdentifierColumnNames();
            $objectIds = array($entityIdentifiers[0] => $objectIds);
        }

        $queryBuilder->setParameters($objectIds);
        $entityColumns = $classMetadata->getIdentifierColumnNames();
        foreach ($entityColumns as $entityColumn) {
            $queryBuilder->andWhere($entityColumn.' = :'.$entityColumn);
        }
        $columnMap = array();
        foreach ($classMetadata->fieldNames as $columnName => $field) {
            $queryBuilder->addSelect(sprintf(
                '%s AS %s',
                $this->auditQuoteStrategy->getColumnName($field, $classMetadata, $this->auditPlatform),
                $this->auditPlatform->quoteSingleIdentifier($field)
            ));
            $columnMap[$field] = $this->auditPlatform->getSQLResultCasing($columnName);
        }
        foreach ($classMetadata->associationMappings as $assoc) {
            if (($assoc['type'] & ClassMetadata::TO_ONE) == 0 || !$assoc['isOwningSide']) {
                continue;
            }
            foreach ($assoc['targetToSourceKeyColumns'] as $sourceCol) {
                $queryBuilder->addSelect($sourceCol);
                $columnMap[$sourceCol] = $this->auditPlatform->getSQLResultCasing($sourceCol);
            }
        }
        $stmt = $queryBuilder->execute();
        $className = $classMetadata->name;
        $objects = array();

        while ($row = $stmt->fetch(Query::HYDRATE_ARRAY)) {
            $revisionIdentifiers = array();
            foreach ($revisionIdentifierNames as $revisionIdentifierName) {
                if (isset($row[$revisionIdentifierName])) {
                    $revisionIdentifiers[$revisionIdentifierName] = $row[$revisionIdentifierName];
                    unset($row[$revisionIdentifierName]);
                }
            }

            $objectRevType = $row[$revisionFieldName];
            unset($row[$revisionFieldName]);

            if ($revisionType !== null) {
                $objectRevType = $revisionType->convertToPHPValue($objectRevType, $this->auditPlatform);
            }

            /** @var RevisionInterface $revision */
            $revision = $this->revisionManager->getRevisionRepository()->find($revisionIdentifiers);
            $entity = $this->objectAuditFactory->createNewAudit($className, $columnMap, $row, $revision, $this);

            $identifiers = array();
            foreach ($classMetadata->identifier as $idField) {
                $identifiers[$idField] = $row[$idField];
            }

            $objects[] = new ObjectAudit(
                $entity,
                $className,
                $identifiers,
                $revision,
                $persistManager,
                $row,
                $objectRevType
            );
        }

        return $objects;
    }

    /**
     * {@inheritdoc}
     */
    public function getInitializeObjectRevision(string $className, $objectIds)
    {
        return $this->getObjectRevision($className, $objectIds);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentObjectRevision(string $className, $objectIds)
    {
        return $this->getObjectRevision($className, $objectIds, 'DESC');
    }

    /**
     * {@inheritdoc}
     */
    public function saveObjectAudit(ObjectAudit $objectAudit)
    {
        $object = $objectAudit->getObject();
        $className = $objectAudit->getClassName();
        $entityData = $objectAudit->getRevisionData();
        $revision = $objectAudit->getRevision();
        $identifiers = $objectAudit->getIdentifiers();

        /** @var EntityManagerInterface $persistManager */
        $persistManager = $objectAudit->getPersistManager();
        $classMetadata = $persistManager->getClassMetadata($className);
        $uow = $persistManager->getUnitOfWork();
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->configuration;

        $params = array($objectAudit->getRevisionType());
        $types = array($configuration->getRevisionTypeFieldType());
        $fields = array();

        foreach ($this->revisionMetadata->getIdentifierValues($revision) as $identifier) {
            $params[] = $identifier;
            $types[] = \PDO::PARAM_INT;
        }

        foreach ($classMetadata->associationMappings as $field => $assoc) {
            if ($classMetadata->isInheritanceTypeJoined() && $classMetadata->isInheritedAssociation($field)) {
                continue;
            }
            if (!(($assoc['type'] & ClassMetadata::TO_ONE) > 0 && $assoc['isOwningSide'])) {
                continue;
            }

            $data = null;
            if (isset($identifiers[$field])) {
                $data = $identifiers[$field];
            } elseif (isset($entityData[$field])) {
                $data = $entityData[$field];
            }

            $relatedId = false;
            if ($data !== null && $uow->isInIdentityMap($data)) {
                $relatedId = $uow->getEntityIdentifier($data);
            }
            $targetClass = $persistManager->getClassMetadata($assoc['targetEntity']);
            foreach ($assoc['sourceToTargetKeyColumns'] as $sourceColumn => $targetColumn) {
                $fields[$sourceColumn] = true;
                if ($data === null) {
                    $params[] = null;
                    $types[] = \PDO::PARAM_STR;
                } else {
                    $params[] = $relatedId ? $relatedId[$targetClass->fieldNames[$targetColumn]] : null;
                    $types[] = $targetClass->getTypeOfColumn($targetColumn);
                }
            }
        }
        foreach ($classMetadata->fieldNames as $field) {
            $columnName = $classMetadata->fieldMappings[$field]['columnName'];
            if (array_key_exists($columnName, $fields)) {
                continue;
            }
            if ($classMetadata->isInheritanceTypeJoined()
                && $classMetadata->isInheritedField($field)
                && !$classMetadata->isIdentifier($field)
            ) {
                continue;
            }
            $params[] = isset($entityData[$field]) ? $entityData[$field] : null;
            $types[] = $classMetadata->fieldMappings[$field]['type'];
        }
        if ($classMetadata->isInheritanceTypeSingleTable()) {
            $params[] = $classMetadata->discriminatorValue;
            $types[] = $classMetadata->discriminatorColumn['type'];
        } elseif ($classMetadata->isInheritanceTypeJoined()
            && $classMetadata->name == $classMetadata->rootEntityName
        ) {
            $params[] = $entityData[$classMetadata->discriminatorColumn['name']];
            $types[] = $classMetadata->discriminatorColumn['type'];
        }
        if ($classMetadata->isInheritanceTypeJoined() && $classMetadata->name != $classMetadata->rootEntityName) {
            $entityData[$classMetadata->discriminatorColumn['name']] = $classMetadata->discriminatorValue;
            $rootClassName = $classMetadata->rootEntityName;
            $rootClassMetadata = $persistManager->getClassMetadata($rootClassName);

            $this->saveObjectAudit(new ObjectAudit(
                $object,
                $rootClassName,
                $rootClassMetadata->getIdentifierValues($object),
                $revision,
                $persistManager,
                $entityData,
                $objectAudit->getRevisionType()
            ));
        }

        $this->auditConnection->executeUpdate($this->getInsertRevisionSQL($classMetadata), $params, $types);
    }

    /**
     * @param string $className
     *
     * @return string
     */
    public function getAuditTableNameForClass(string $className)
    {
        $className = ClassUtils::getRealClass($className);

        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->persistManager->getClassMetadata($className);
        $tableName = $classMetadata->getTableName();
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->getConfiguration();

        return $configuration->getTablePrefix().$tableName.$configuration->getTableSuffix();
    }

    /**
     * @param string $className
     * @param mixed  $objectIds
     * @param string $sort
     *
     * @throws ObjectNotAuditedException
     * @throws ObjectAuditNotFoundException
     *
     * @return null|object
     */
    protected function getObjectRevision(string $className, $objectIds, $sort = 'ASC')
    {
        $className = ClassUtils::getRealClass($className);

        if (!$this->objectAuditMetadataFactory->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        /** @var EntityManagerInterface $persistManager */
        $persistManager = $this->persistManager;

        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $persistManager->getClassMetadata($className);
        $entityTableName = $this->getAuditTableNameForClass($className);

        if (!is_array($objectIds)) {
            $entityIdentifiers = $classMetadata->getIdentifierColumnNames();
            $objectIds = array($entityIdentifiers[0] => $objectIds);
        }

        $queryBuilder = $this->auditConnection->createQueryBuilder()
            ->setMaxResults(1)
            ->from($entityTableName, 'e');

        $entityColumns = $classMetadata->getIdentifierColumnNames();
        foreach ($entityColumns as $entityColumn) {
            $queryBuilder->andWhere($entityColumn.' = :'.$entityColumn);
        }

        $revisionIdMap = array();
        foreach ($this->revisionMetadata->identifier as $idField) {
            $revisionIdName = $this->getRevisionColumnName($idField);
            $revisionIdMap[$revisionIdName] = $idField;
            $queryBuilder->select($revisionIdName);
            $queryBuilder->orderBy($revisionIdName, $sort);
        }

        $queryBuilder->setParameters($objectIds);
        $revisionIds = $queryBuilder->execute()->fetch(\PDO::FETCH_ASSOC);

        if ($revisionIds === false) {
            throw ObjectAuditNotFoundException::forObjectIdentifiers($className, $objectIds);
        }

        foreach ($revisionIds as $fieldName => $fieldValue) {
            $newFieldName = $revisionIdMap[$fieldName];
            $revisionIds[$newFieldName] = $fieldValue;
            unset($revisionIds[$fieldName]);
        }

        return $this->revisionManager->getRevisionRepository()->find($revisionIds);
    }

    /**
     * @param string $columnName
     *
     * @return string
     */
    protected function getRevisionColumnName(string $columnName): string
    {
        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->configuration;

        return $configuration->getRevisionIdFieldPrefix().$columnName.$configuration->getRevisionIdFieldSuffix();
    }

    /**
     * @param string       $tableName
     * @param array        $foreignKeys
     * @param array        $revisionIdentifiers
     * @param array        $identifierColumnNames
     * @param Connection   $connection
     * @param QueryBuilder $parentQueryBuilder
     *
     * @return QueryBuilder
     */
    protected function createBelongingToOtherEntitiesQueryBuilder(
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
            $notEqualParts[] = $column.' <> '.$parentQueryBuilder->createPositionalParameter($value);
            $nullParts[] = $column.' IS NULL';
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
     * @param string       $tableName
     * @param array        $revisionIdentifiers
     * @param array        $identifierColumnNames
     * @param Connection   $connection
     * @param QueryBuilder $parentQueryBuilder
     *
     * @return QueryBuilder
     */
    protected function createDeletedRevisionsQueryBuilder(
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

        $type = Type::getType($configuration->getRevisionTypeFieldType());
        $queryBuilder->andWhere(sprintf(
            'sd.%s = %s',
            $configuration->getRevisionTypeFieldName(),
            $parentQueryBuilder->createPositionalParameter(
                $type->convertToDatabaseValue(RevisionInterface::ACTION_DELETE, $this->auditPlatform)
            )
        ));

        //ids
        foreach ($identifierColumnNames as $name) {
            $queryBuilder->andWhere(sprintf('sd.%1$s = t.%1$s', $name));
        }

        return $queryBuilder;
    }

    /**
     * @param ClassMetadata $classMetadata
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getInsertRevisionSQL(ClassMetadata $classMetadata)
    {
        if (!isset($this->insertRevisionSQL[$classMetadata->name])) {
            $placeholders = array('?', '?');
            $auditTableName = $this->getAuditTableNameForClass($classMetadata->name);
            /** @var ORMAuditConfiguration $configuration */
            $configuration = $this->configuration;
            $params = array($configuration->getRevisionTypeFieldName());

            foreach ($this->revisionMetadata->getIdentifierFieldNames() as $identifier) {
                $params[] = $this->getRevisionColumnName($identifier);
            }

            $fields = array();
            foreach ($classMetadata->associationMappings as $field => $assoc) {
                if ($classMetadata->isInheritanceTypeJoined() && $classMetadata->isInheritedAssociation($field)) {
                    continue;
                }
                if (($assoc['type'] & ClassMetadata::TO_ONE) > 0 && $assoc['isOwningSide']) {
                    foreach ($assoc['targetToSourceKeyColumns'] as $sourceCol) {
                        $fields[$sourceCol] = true;
                        $params[] = $sourceCol;
                        $placeholders[] = '?';
                    }
                }
            }
            foreach ($classMetadata->fieldNames as $field) {
                $columnName = $classMetadata->fieldMappings[$field]['columnName'];
                if (array_key_exists($columnName, $fields)) {
                    continue;
                }
                if ($classMetadata->isInheritanceTypeJoined()
                    && $classMetadata->isInheritedField($field)
                    && !$classMetadata->isIdentifier($field)
                ) {
                    continue;
                }
                $type = Type::getType($classMetadata->fieldMappings[$field]['type']);
                $placeholders[] = (!empty($classMetadata->fieldMappings[$field]['requireSQLConversion']))
                    ? $type->convertToDatabaseValueSQL('?', $this->auditPlatform)
                    : '?';
                $params[] = $this->auditQuoteStrategy->getColumnName($field, $classMetadata, $this->auditPlatform);
            }
            if (($classMetadata->isInheritanceTypeJoined() && $classMetadata->rootEntityName == $classMetadata->name)
                || $classMetadata->isInheritanceTypeSingleTable()
            ) {
                $params[] = $classMetadata->discriminatorColumn['name'];
                $placeholders[] = '?';
            }

            $sql = 'INSERT INTO '.$auditTableName.' ('.implode(', ', $params).') VALUES ('.implode(', ', $placeholders).')';
            $this->insertRevisionSQL[$classMetadata->name] = $sql;
        }

        return $this->insertRevisionSQL[$classMetadata->name];
    }

    /**
     * @return array
     */
    protected function getAuditRevisionColumns(): array
    {
        if ($this->auditRevisionColumns === null) {
            $this->auditRevisionColumns = array();
            $revisionColumns = $this->revisionMetadata->getIdentifierColumnNames();
            foreach ($revisionColumns as $revisionColumn) {
                $this->auditRevisionColumns[] = $this->getRevisionColumnName($revisionColumn);
            }
        }

        return $this->auditRevisionColumns;
    }
}
