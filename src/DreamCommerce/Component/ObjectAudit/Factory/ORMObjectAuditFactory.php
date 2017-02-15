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

namespace DreamCommerce\Component\ObjectAudit\Factory;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\PersistentCollection;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectAuditDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectAuditNotFoundException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Manager\RevisionManagerInterface;
use DreamCommerce\Component\ObjectAudit\Model\AuditCollection;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use InvalidArgumentException;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use RuntimeException;

final class ORMObjectAuditFactory implements ObjectAuditFactoryInterface
{
    /**
     * @var RevisionManagerInterface
     */
    private $revisionManager;

    /**
     * Entity cache to prevent circular references.
     *
     * @var array
     */
    private $entityCache = array();

    /**
     * @param RevisionManagerInterface $revisionManager
     */
    public function __construct(RevisionManagerInterface $revisionManager)
    {
        $this->revisionManager = $revisionManager;
    }

    /**
     * {@inheritdoc}
     */
    public function createNew()
    {
        throw new InvalidArgumentException('Create empty audit object is not supported');
    }

    /**
     * {@inheritdoc}
     */
    public function clearAuditCache()
    {
        $this->entityCache = array();
    }

    /**
     * Simplified and stolen code from UnitOfWork::createEntity.
     *
     * @param string                      $className
     * @param array                       $columnMap
     * @param array                       $data
     * @param RevisionInterface           $revision
     * @param ObjectAuditManagerInterface $objectAuditManager
     *
     * @throws ObjectAuditDeletedException
     * @throws ObjectAuditNotFoundException
     * @throws ObjectNotAuditedException
     * @throws DBALException
     * @throws MappingException
     * @throws ORMException
     * @throws \Exception
     *
     * @return object
     */
    public function createNewAudit(string $className, array $columnMap, array $data, RevisionInterface $revision,
                                   ObjectAuditManagerInterface $objectAuditManager)
    {
        $configuration = $objectAuditManager->getConfiguration();
        $objectMetadataFactory = $objectAuditManager->getObjectAuditMetadataFactory();
        /** @var ClassMetadata $revisionMetadata */
        $revisionMetadata = $this->revisionManager->getRevisionMetadata();

        $persistManager = $objectAuditManager->getPersistManager();
        /** @var EntityManagerInterface $persistManager */
        $classMetadata = $persistManager->getClassMetadata($className);
        $cacheKey = $this->createEntityCacheKey($classMetadata, $revisionMetadata, $data, $revision);
        $proxyFactory = new LazyLoadingValueHolderFactory();

        if (isset($this->entityCache[$cacheKey])) {
            return $this->entityCache[$cacheKey];
        }

        if (!$classMetadata->isInheritanceTypeNone()) {
            if (!isset($data[$classMetadata->discriminatorColumn['name']])) {
                throw new RuntimeException('Expecting discriminator value in data set.');
            }
            $discriminator = $data[$classMetadata->discriminatorColumn['name']];
            if (!isset($classMetadata->discriminatorMap[$discriminator])) {
                throw new RuntimeException("No mapping found for [{$discriminator}].");
            }

            if ($classMetadata->discriminatorValue) {
                $entity = $persistManager->getClassMetadata($classMetadata->discriminatorMap[$discriminator])->newInstance();
            } else {
                //a complex case when ToOne binding is against AbstractEntity having no discriminator
                $identifiers = array();

                foreach ($classMetadata->identifier as $field) {
                    $identifiers[$classMetadata->getColumnName($field)] = $data[$field];
                }

                $className = $classMetadata->discriminatorMap[$discriminator];

                return $proxyFactory->createProxy(
                    $className,
                    function (& $wrappedObject, $proxy, $method, $parameters, & $initializer) use($objectAuditManager, $revision, $identifiers, $discriminator, $className) {
                        $wrappedObject = $objectAuditManager->findObjectByRevision($className, $identifiers, $revision);
                        $initializer = null;
                    }
                );
            }
        } else {
            $entity = $classMetadata->newInstance();
        }

        //cache the entity to prevent circular references
        $this->entityCache[$cacheKey] = $entity;

        $connection = $persistManager->getConnection();
        foreach ($data as $field => $value) {
            if (isset($classMetadata->fieldMappings[$field])) {
                $value = $connection->convertToPHPValue($value, $classMetadata->fieldMappings[$field]['type']);
                $classMetadata->reflFields[$field]->setValue($entity, $value);
            }
        }

        $uow = $persistManager->getUnitOfWork();
        $options = array('threatDeletionsAsExceptions' => true);

        foreach ($classMetadata->associationMappings as $field => $assoc) {
            // Check if the association is not among the fetch-joined associations already.
            if (isset($hints['fetched'][$className][$field])) {
                continue;
            }

            /** @var ClassMetadata $targetClass */
            $targetClass = $persistManager->getClassMetadata($assoc['targetEntity']);
            $isAudited = $objectMetadataFactory->isClassAudited($assoc['targetEntity']);

            if ($assoc['type'] & ClassMetadata::TO_ONE) {
                $value = null;

                if ($isAudited && $configuration->isLoadAuditedObjects()) {
                    if ($assoc['isOwningSide']) {
                        // Primary Key. Used for audit tables queries.
                        $identifiers = array();
                        foreach ($assoc['targetToSourceKeyColumns'] as $foreign => $local) {
                            $identifiers[$foreign] = $data[$columnMap[$local]];
                        }
                        $identifiers = array_filter($identifiers);

                        if (!empty($identifiers)) {
                            try {
                                $value = $objectAuditManager->findObjectByRevision(
                                    $targetClass->name,
                                    $identifiers,
                                    $revision,
                                    $options
                                );
                            } catch (ObjectAuditDeletedException $e) {
                                $value = null;
                            } catch (ObjectAuditNotFoundException $e) {
                                // The entity does not have any revision yet. So let's get the actual state of it.
                                $value = $persistManager->getRepository($targetClass->name)->findOneBy($identifiers);
                            }
                        }
                    } else {
                        // Primary Field. Used when fallback to Doctrine finder.
                        $pf = array();

                        /** @var ClassMetadata $otherEntityMeta */
                        $otherEntityAssoc = $persistManager->getClassMetadata($assoc['targetEntity'])->associationMappings[$assoc['mappedBy']];

                        $fields = array();
                        foreach ($otherEntityAssoc['targetToSourceKeyColumns'] as $local => $foreign) {
                            $fields[$foreign] = $pf[$otherEntityAssoc['fieldName']] = $data[$classMetadata->getFieldName($local)];
                        }

                        $objects = $objectAuditManager->findObjectsByFieldsAndRevision(
                            $targetClass->name,
                            $fields,
                            null,
                            $revision,
                            $options
                        );

                        if (count($objects) == 0) {
                            // The entity does not have any revision yet. So let's get the actual state of it.
                            $value = $persistManager->getRepository($targetClass->name)->findOneBy($pf);
                        } elseif (count($objects) == 1) {
                            try {
                                $value = $objects[0];
                            } catch (ObjectAuditDeletedException $e) {
                                $value = null;
                            }
                        } else {
                            throw new \Exception(); // TODO
                        }
                    }
                } elseif (!$isAudited && $configuration->isLoadNativeObjects()) {
                    if ($assoc['isOwningSide']) {
                        $associatedId = array();
                        foreach ($assoc['targetToSourceKeyColumns'] as $targetColumn => $srcColumn) {
                            $joinColumnValue = isset($data[$columnMap[$srcColumn]]) ? $data[$columnMap[$srcColumn]] : null;
                            if ($joinColumnValue !== null) {
                                $associatedId[$targetClass->fieldNames[$targetColumn]] = $joinColumnValue;
                            }
                        }
                        if (!empty($associatedId)) {
                            $value = $persistManager->getReference($targetClass->name, $associatedId);
                        }
                    } else {
                        // Inverse side of x-to-one can never be lazy
                        $value = $uow->getEntityPersister($assoc['targetEntity'])
                            ->loadOneToOneEntity($assoc, $entity);
                    }
                }

                $classMetadata->reflFields[$field]->setValue($entity, $value);
            } elseif ($assoc['type'] & ClassMetadata::ONE_TO_MANY) {
                $collection = new ArrayCollection();

                if ($isAudited && $configuration->isLoadAuditedCollections()) {
                    $foreignKeys = array();
                    foreach ($targetClass->associationMappings[$assoc['mappedBy']]['sourceToTargetKeyColumns'] as $local => $foreign) {
                        $field = $classMetadata->getFieldForColumn($foreign);
                        $foreignKeys[$local] = $classMetadata->reflFields[$field]->getValue($entity);
                    }

                    $indexBy = null;
                    if (isset($assoc['indexBy'])) {
                        $indexBy = $assoc['indexBy'];
                    }

                    $collection = new AuditCollection(
                        $targetClass->name,
                        $foreignKeys,
                        $indexBy,
                        $revision,
                        $objectAuditManager
                    );
                } elseif (!$isAudited && $configuration->isLoadNativeCollections()) {
                    $collection = new PersistentCollection($persistManager, $targetClass, new ArrayCollection());

                    $uow->getEntityPersister($assoc['targetEntity'])
                        ->loadOneToManyCollection($assoc, $entity, $collection);
                }

                $classMetadata->reflFields[$assoc['fieldName']]->setValue($entity, $collection);
            } else {
                // Inject collection
                $classMetadata->reflFields[$field]->setValue($entity, new ArrayCollection());
            }
        }

        return $entity;
    }

    /**
     * @param ClassMetadata     $classMetadata
     * @param ClassMetadata     $revisionMetadata
     * @param array             $data
     * @param RevisionInterface $revision
     *
     * @return string
     */
    private function createEntityCacheKey(ClassMetadata $classMetadata, ClassMetadata $revisionMetadata, array $data, RevisionInterface $revision)
    {
        $revisionIds = $revisionMetadata->getIdentifierValues($revision);
        ksort($revisionIds);

        $keyParts = array();
        foreach ($classMetadata->getIdentifierFieldNames() as $name) {
            if ($classMetadata->hasAssociation($name)) {
                if ($classMetadata->isSingleValuedAssociation($name)) {
                    $name = $classMetadata->getSingleAssociationJoinColumnName($name);
                } else {
                    // Doctrine should throw a mapping exception if an identifier
                    // that is an association is not single valued, but just in case.
                    throw new RuntimeException('Multiple valued association identifiers not supported');
                }
            }
            $keyParts[$name] = $data[$name];
        }
        ksort($keyParts);

        return $classMetadata->name.'_'.implode('_', array_values($keyParts)).'_'.implode('_', array_values($revisionIds));
    }
}
