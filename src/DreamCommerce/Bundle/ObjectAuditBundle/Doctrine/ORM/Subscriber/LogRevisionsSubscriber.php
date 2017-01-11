<?php
/*
 * (c) 2011 SimpleThings GmbH
 *
 * @package DreamCommerce\Component\ObjectAudit
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
use Doctrine\ORM\Events;
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
        return [
            Events::onFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        /** @var ORMAuditManager $auditObjectManager */
        $auditObjectManager = $this->container->get('dream_commerce_object_audit.manager');

        /** @var ORMAuditConfiguration $configuration */
        $configuration = $auditObjectManager->getConfiguration();

        $entityManager = $eventArgs->getEntityManager();
        $uow = $entityManager->getUnitOfWork();
        $ignoredProperties = $configuration->getGlobalIgnoreProperties();
        $currentRevision = $auditObjectManager->getCurrentRevision();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $className = get_class($entity);
            if (!$configuration->isClassAudited($className)) {
                continue;
            }
            $entityManager = $eventArgs->getEntityManager();

            $changedObject = new ChangedObject(
                $entity,
                $currentRevision,
                $this->getOriginalEntityData($entity, $entityManager),
                RevisionInterface::ACTION_INSERT,
                $entityManager
            );
            $this->eventDispatcher->dispatch(
                DreamCommerceObjectAuditEvents::OBJECT_CHANGED,
                new ChangedObjectEvent($changedObject)
            );

            $auditObjectManager->saveObjectRevisionData($changedObject);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $className = get_class($entity);
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
            $entityData = array_merge(
                $this->getOriginalEntityData($entity, $entityManager),
                $uow->getEntityIdentifier($entity)
            );

            $changedObject = new ChangedObject(
                $entity,
                $currentRevision,
                $entityData,
                RevisionInterface::ACTION_UPDATE,
                $entityManager
            );
            $this->eventDispatcher->dispatch(
                DreamCommerceObjectAuditEvents::OBJECT_CHANGED,
                new ChangedObjectEvent($changedObject)
            );

            $auditObjectManager->saveObjectRevisionData($changedObject);
        }

        $processedEntities = [];

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $className = get_class($entity);
            if (!$configuration->isClassAudited($className)) {
                continue;
            }

            //doctrine is fine deleting elements multiple times. We are not.
            $hash = $this->getHash($entity, $uow);
            if (in_array($hash, $processedEntities)) {
                continue;
            }
            $processedEntities[] = $hash;

            $entityData = array_merge(
                $this->getOriginalEntityData($entity, $entityManager),
                $uow->getEntityIdentifier($entity)
            );

            $changedObject = new ChangedObject(
                $entity,
                $currentRevision,
                $entityData,
                RevisionInterface::ACTION_DELETE,
                $entityManager
            );
            $this->eventDispatcher->dispatch(
                DreamCommerceObjectAuditEvents::OBJECT_CHANGED,
                new ChangedObjectEvent($changedObject)
            );

            $auditObjectManager->saveObjectRevisionData($changedObject);
        }

        $auditObjectManager->saveCurrentRevision();
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
     * @param UnitOfWork $uow
     *
     * @return string
     */
    private function getHash($entity, UnitOfWork $uow)
    {
        return implode(
            ' ',
            array_merge(
                array(ClassUtils::getRealClass(get_class($entity))),
                $uow->getEntityIdentifier($entity)
            )
        );
    }
}
