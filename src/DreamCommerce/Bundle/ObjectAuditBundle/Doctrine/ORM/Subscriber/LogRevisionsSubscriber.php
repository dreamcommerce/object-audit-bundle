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

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORM\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORMAuditConfiguration;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORMAuditManager;
use DreamCommerce\Bundle\ObjectAuditBundle\DreamCommerceObjectAuditEvents;
use DreamCommerce\Bundle\ObjectAuditBundle\Event\ChangedObjectEvent;
use DreamCommerce\Component\ObjectAudit\Model\ChangedObject;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LogRevisionsSubscriber implements EventSubscriber
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ChangedObject[]
     */
    private $changedObjects = [];

    /**
     * @param ContainerInterface       $container
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(ContainerInterface $container, EventDispatcherInterface $eventDispatcher)
    {
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush
        );
    }

    /**
     * @param PostFlushEventArgs $eventArgs
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function postFlush(PostFlushEventArgs $eventArgs)
    {
        if(count($this->changedObjects) > 0) {
            /** @var ORMAuditManager $auditObjectManager */
            $auditObjectManager = $this->container->get('dream_commerce_object_audit.manager');
            if($auditObjectManager === null) {
                return;
            }

            foreach ($this->changedObjects as $changedObject) {
                /** @var EntityManagerInterface $entityManager */
                $entityManager = $changedObject->getPersistManager();
                $uow = $entityManager->getUnitOfWork();

                $revisionData = array_merge(
                    $changedObject->getRevisionData(),
                    $changedObject->getIdentifiers()
                );

                $changedIdentifiers = $uow->getEntityIdentifier($changedObject->getObject());
                if($changedIdentifiers !== null) {
                    $revisionData = array_merge(
                        $revisionData,
                        $changedIdentifiers
                    );
                }

                $changedObject->setRevisionData($revisionData);

                $this->eventDispatcher->dispatch(
                    DreamCommerceObjectAuditEvents::OBJECT_CHANGED,
                    new ChangedObjectEvent($changedObject)
                );

                $auditObjectManager->saveObjectRevisionData($changedObject);
            }

            $this->changedObjects = [];
        }
    }

    /**
     * @param OnFlushEventArgs $eventArgs
     */
    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        /** @var ORMAuditManager $auditObjectManager */
        $auditObjectManager = $this->container->get('dream_commerce_object_audit.manager');
        if($auditObjectManager === null) {
            return;
        }

        /** @var ORMAuditConfiguration $configuration */
        $configuration = $auditObjectManager->getConfiguration();

        $entityManager = $eventArgs->getEntityManager();
        $uow = $entityManager->getUnitOfWork();
        $ignoredProperties = $configuration->getGlobalIgnoreProperties();
        $currentRevision = $auditObjectManager->getCurrentRevision();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $className = ClassUtils::getRealClass(get_class($entity));
            if (!$configuration->isClassAudited($className)) {
                continue;
            }
            $entityManager = $eventArgs->getEntityManager();
            $classMetadata = $entityManager->getClassMetadata($className);

            $this->changedObjects[spl_object_hash($entity)] = new ChangedObject(
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
            if (!$configuration->isClassAudited($className)) {
                continue;
            }

            $uow = $entityManager->getUnitOfWork();

            // get changes => should be already computed here (is a listener)
            $changeset = $uow->getEntityChangeSet($entity);
            foreach ($ignoredProperties as $property) {
                if (isset($changeset[$property])) {
                    unset($changeset[$property]);
                }
            }

            // if we have no changes left => don't create revision log
            if (count($changeset) == 0) {
                return;
            }

            $classMetadata = $entityManager->getClassMetadata($className);

            $this->changedObjects[spl_object_hash($entity)] = new ChangedObject(
                $entity,
                $className,
                $classMetadata->getIdentifierValues($entity),
                $currentRevision,
                $entityManager,
                $this->getOriginalEntityData($entity, $entityManager),
                RevisionInterface::ACTION_UPDATE
            );
        }

        $processedEntities = array();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $className = ClassUtils::getRealClass(get_class($entity));
            if (!$configuration->isClassAudited($className)) {
                continue;
            }

            //doctrine is fine deleting elements multiple times. We are not.
            $hash = $this->getHash($entity, $className, $uow);
            if (in_array($hash, $processedEntities)) {
                continue;
            }
            $processedEntities[] = $hash;
            $classMetadata = $entityManager->getClassMetadata($className);

            $this->changedObjects[spl_object_hash($entity)] = new ChangedObject(
                $entity,
                $className,
                $classMetadata->getIdentifierValues($entity),
                $currentRevision,
                $entityManager,
                $this->getOriginalEntityData($entity, $entityManager),
                RevisionInterface::ACTION_DELETE
            );
        }

        if(count($this->changedObjects) > 0) {
            $auditObjectManager->saveCurrentRevision();
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
}
