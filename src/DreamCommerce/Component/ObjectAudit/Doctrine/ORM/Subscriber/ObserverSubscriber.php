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

namespace DreamCommerce\Component\ObjectAudit\Doctrine\ORM\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Persisters\Entity\BasicEntityPersister;
use Doctrine\ORM\Persisters\Entity\EntityPersister;
use Doctrine\ORM\UnitOfWork;
use DreamCommerce\Component\ObjectAudit\Exception\NotDefinedException;
use DreamCommerce\Component\ObjectAudit\Model\ObjectAudit;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ObjectAuditRegistry;

class ObserverSubscriber implements EventSubscriber
{
    /**
     * @var ObjectAuditRegistry
     */
    protected $objectAuditRegistry;

    /**
     * @var ObjectAudit[]
     */
    private $objects = array();

    /**
     * @param ObjectAuditRegistry $objectAuditRegistry
     */
    public function __construct(ObjectAuditRegistry $objectAuditRegistry)
    {
        $this->objectAuditRegistry = $objectAuditRegistry;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    /**
     * @param PostFlushEventArgs $eventArgs
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function postFlush(PostFlushEventArgs $eventArgs)
    {
        if (count($this->objects) > 0) {
            $entityManager = $eventArgs->getEntityManager();
            try {
                $objectAuditManager = $this->getObjectAuditRegistry()->getByPersistManager($entityManager);
            } catch (NotDefinedException $exc) {
                return;
            }

            foreach ($this->objects as $objectAudit) {
                $uow = $entityManager->getUnitOfWork();
                $className = $objectAudit->getClassName();

                $revisionData = array_merge(
                    $objectAudit->getData(),
                    $objectAudit->getIdentifiers()
                );

                $persister = $uow->getEntityPersister($className);
                $updateData = $this->prepareUpdateData($entityManager, $persister, $objectAudit->getObject());

                if (is_array($updateData) && count($updateData) > 0) {
                    $revisionData = array_merge(
                        $revisionData,
                        $updateData
                    );
                }

                $object = $objectAudit->getObject();
                if ($uow->isInIdentityMap($object)) {
                    $changedIdentifiers = $uow->getEntityIdentifier($object);
                    if ($changedIdentifiers !== null) {
                        $revisionData = array_merge(
                            $revisionData,
                            $changedIdentifiers
                        );
                    }
                }

                $objectAudit->setData($revisionData);
                $objectAuditManager->saveAudit($objectAudit);
            }

            if (count($this->objects) > 0) {
                $revisionManager = $objectAuditManager->getRevisionManager();
                $revisionManager->resetRevision();
            }

            $this->objects = array();
        }
    }

    /**
     * @param OnFlushEventArgs $eventArgs
     *
     * @throws \Exception
     */
    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        $entityManager = $eventArgs->getEntityManager();
        try {
            $objectAuditManager = $this->getObjectAuditRegistry()->getByPersistManager($entityManager);
        } catch (NotDefinedException $e) {
            return;
        }
        $objectMetadataFactory = $objectAuditManager->getMetadataFactory();
        $uow = $entityManager->getUnitOfWork();
        $configuration = $objectAuditManager->getConfiguration();
        $globalIgnoredProperties = $configuration->getIgnoreProperties();
        $revisionManager = $objectAuditManager->getRevisionManager();
        $currentRevision = $revisionManager->getRevision();
        $processedEntities = array();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $className = ClassUtils::getRealClass(get_class($entity));
            if (!$objectMetadataFactory->isAudited($className)) {
                continue;
            }

            //doctrine is fine deleting elements multiple times. We are not.
            $hash = $this->getHash($entity, $className, $uow);
            if (in_array($hash, $processedEntities)) {
                continue;
            }
            $processedEntities[] = $hash;
            $classMetadata = $entityManager->getClassMetadata($className);

            $this->objects[spl_object_hash($entity)] = new ObjectAudit(
                $entity,
                $className,
                $classMetadata->getIdentifierValues($entity),
                $currentRevision,
                $entityManager,
                $this->getOriginalEntityData($entity, $entityManager),
                RevisionInterface::ACTION_DELETE
            );
        }

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $className = ClassUtils::getRealClass(get_class($entity));
            if (!$objectMetadataFactory->isAudited($className)) {
                continue;
            }
            $entityManager = $eventArgs->getEntityManager();
            $classMetadata = $entityManager->getClassMetadata($className);

            $this->objects[spl_object_hash($entity)] = new ObjectAudit(
                $entity,
                $className,
                $classMetadata->getIdentifierValues($entity),
                $currentRevision,
                $entityManager,
                $this->getOriginalEntityData($entity, $entityManager),
                RevisionInterface::ACTION_INSERT
            );
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $className = ClassUtils::getRealClass(get_class($entity));
            if (!$objectMetadataFactory->isAudited($className)) {
                continue;
            }

            $uow = $entityManager->getUnitOfWork();

            // get changes => should be already computed here (is a listener)
            $changeset = $uow->getEntityChangeSet($entity);
            foreach ($globalIgnoredProperties as $property) {
                if (isset($changeset[$property])) {
                    unset($changeset[$property]);
                }
            }

            $objectAuditMetadata = $objectMetadataFactory->getMetadataFor($className);
            foreach ($objectAuditMetadata->ignoredProperties as $property) {
                if (isset($changeset[$property])) {
                    unset($changeset[$property]);
                }
            }

            // if we have no changes left => don't create revision log
            if (count($changeset) == 0) {
                unset($this->objects[spl_object_hash($entity)]);
                continue;
            }

            $classMetadata = $entityManager->getClassMetadata($className);

            $this->objects[spl_object_hash($entity)] = new ObjectAudit(
                $entity,
                $className,
                $classMetadata->getIdentifierValues($entity),
                $currentRevision,
                $entityManager,
                $this->getOriginalEntityData($entity, $entityManager),
                RevisionInterface::ACTION_UPDATE
            );
        }

        if (count($this->objects) > 0) {
            $revisionManager->save();
            $auditPersistManager = $revisionManager->getPersistManager();

            if ($auditPersistManager != $entityManager) {
                $auditPersistManager->flush();
            }
        }
    }

    /**
     * get original entity data, including versioned field, if "version" constraint is used.
     *
     * @param mixed                  $entity
     * @param EntityManagerInterface $entityManager
     *
     * @return array
     */
    private function getOriginalEntityData($entity, EntityManagerInterface $entityManager)
    {
        $class = $entityManager->getClassMetadata(get_class($entity));
        $data = $entityManager->getUnitOfWork()->getOriginalEntityData($entity);
        if ($class->isVersioned) {
            $versionField = $class->versionField;
            $data[$versionField] = $class->reflFields[$versionField]->getValue($entity);
        }

        return $data;
    }

    /**
     * @param object     $entity
     * @param string     $className
     * @param UnitOfWork $uow
     *
     * @return string
     */
    private function getHash($entity, string $className, UnitOfWork $uow)
    {
        return implode(
            ' ',
            array_merge(
                array($className),
                $uow->getEntityIdentifier($entity)
            )
        );
    }

    /**
     * Modified version of BasicEntityPersister::prepareUpdateData()
     * git revision d9fc5388f1aa1751a0e148e76b4569bd207338e9 (v2.5.3)
     *
     * @license MIT
     *
     * @author  Roman Borschel <roman@code-factory.org>
     * @author  Giorgio Sironi <piccoloprincipeazzurro@gmail.com>
     * @author  Benjamin Eberlei <kontakt@beberlei.de>
     * @author  Alexander <iam.asm89@gmail.com>
     * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
     * @author  Rob Caiger <rob@clocal.co.uk>
     * @author  Simon Mönch <simonmoench@gmail.com>
     *
     * @param EntityPersister|BasicEntityPersister $persister
     * @param object                               $entity
     *
     * @return array
     */
    private function prepareUpdateData(EntityManagerInterface $em, EntityPersister $persister, $entity)
    {
        $uow = $em->getUnitOfWork();
        $classMetadata = $persister->getClassMetadata();

        $versionField = null;
        $result = array();

        if (($versioned = $classMetadata->isVersioned) != false) {
            $versionField = $classMetadata->versionField;
        }

        foreach ($uow->getEntityChangeSet($entity) as $field => $change) {
            if (isset($versionField) && $versionField == $field) {
                continue;
            }

            if (isset($classMetadata->embeddedClasses[$field])) {
                continue;
            }

            $newVal = $change[1];

            if (! isset($classMetadata->associationMappings[$field])) {
                $columnName = $classMetadata->columnNames[$field];
                $fieldName = $classMetadata->getFieldName($columnName);

                $result[$persister->getOwningTable($field)][$fieldName] = $newVal;
                continue;
            }

            $assoc = $classMetadata->associationMappings[$field];

            // Only owning side of x-1 associations can have a FK column.
            if (! $assoc['isOwningSide'] || ! ($assoc['type'] & ClassMetadata::TO_ONE)) {
                continue;
            }

            if ($newVal !== null) {
                if ($uow->isScheduledForInsert($newVal)) {
                    $newVal = null;
                }
            }

            $newValId = null;

            if ($newVal !== null) {
                if (! $uow->isInIdentityMap($newVal)) {
                    continue;
                }
                $newValId = $uow->getEntityIdentifier($newVal);
            }

            $targetClass = $em->getClassMetadata($assoc['targetEntity']);
            $owningTable = $persister->getOwningTable($field);

            foreach ($assoc['joinColumns'] as $joinColumn) {
                $sourceColumn = $joinColumn['name'];
                $targetColumn = $joinColumn['referencedColumnName'];
                $fieldName = $classMetadata->getFieldName($sourceColumn);

                $result[$owningTable][$fieldName] = $newValId
                    ? $newValId[$targetClass->getFieldForColumn($targetColumn)]
                    : null;
            }
        }

        $className = $classMetadata->getName();
        $className = substr($className, strrpos($className, '\\') + 1);

        if (!isset($result[$className])) {
            return array();
        }

        return $result[$className];
    }

    protected function getObjectAuditRegistry(): ObjectAuditRegistry
    {
        return $this->objectAuditRegistry;
    }
}
