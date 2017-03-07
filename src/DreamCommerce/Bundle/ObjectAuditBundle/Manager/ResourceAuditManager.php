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

namespace DreamCommerce\Bundle\ObjectAuditBundle\Manager;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Bundle\ObjectAuditBundle\Metadata\ResourceAuditMetadataFactory;
use DreamCommerce\Component\ObjectAudit\Exception\NotDefinedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectAuditDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectAuditNotFoundException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Exception\ResourceAuditNotFoundException;
use DreamCommerce\Component\ObjectAudit\Exception\ResourceDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ResourceNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Manager\ResourceAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Model\ResourceAudit;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ObjectAuditRegistry;
use Sylius\Component\Resource\Metadata\RegistryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ResourceAuditManager implements ResourceAuditManagerInterface
{
    /**
     * @var ObjectAuditRegistry
     */
    protected $objectAuditRegistry;

    /**
     * @var RegistryInterface
     */
    protected $resourceRegistry;

    /**
     * @var ResourceAuditMetadataFactory
     */
    protected $resourceAuditMetadataFactory;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ObjectAuditRegistry          $objectAuditRegistry
     * @param RegistryInterface            $resourceRegistry
     * @param ResourceAuditMetadataFactory $resourceAuditMetadataFactory
     * @param ContainerInterface           $container
     */
    public function __construct(ObjectAuditRegistry $objectAuditRegistry,
                                RegistryInterface $resourceRegistry,
                                ResourceAuditMetadataFactory $resourceAuditMetadataFactory,
                                ContainerInterface $container
    ) {
        $this->objectAuditRegistry = $objectAuditRegistry;
        $this->resourceRegistry = $resourceRegistry;
        $this->resourceAuditMetadataFactory = $resourceAuditMetadataFactory;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function find(string $resourceName, int $resourceId, RevisionInterface $revision, array $options = array())
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceName);

        if (!$objectAuditManager->getMetadataFactory()->isAudited($className)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        try {
            $object = $objectAuditManager->find($className, $resourceId, $revision, $options);
        } catch (ObjectAuditDeletedException $exception) {
            throw ResourceDeletedException::forObjectDeletedException($exception, $resourceName);
        } catch (ObjectAuditNotFoundException $exception) {
            throw ResourceAuditNotFoundException::forObjectNotFoundException($exception, $resourceName);
        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function findByFieldsAndRevision(string $resourceName, array $fields, RevisionInterface $revision, array $options = array()): array
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceName);
        if (!$objectAuditManager->getMetadataFactory()->isAudited($className)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        return $objectAuditManager->findByFieldsAndRevision($className, $fields, $revision, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllChangesAtRevision(RevisionInterface $revision, array $options = array()): array
    {
        $result = array();
        foreach ($this->resourceAuditMetadataFactory->getAllResourceNames() as $resourceName) {
            $result = array_merge(
                $result,
                $this->findChangesAtRevision($resourceName, $revision, $options)
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findChangesAtRevision(string $resourceName, RevisionInterface $revision, array $options = array()): array
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceName);

        if (!$objectAuditManager->getMetadataFactory()->isAudited($className)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        $persistManager = $objectAuditManager->getPersistManager();

        try {
            $rows = $objectAuditManager->findChangesAtRevision($className, $revision, $options);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        }

        foreach ($rows as $k => $row) {
            /** @var ResourceInterface $object */
            $object = $row->getObject();

            $rows[$k] = new ResourceAudit(
                $object,
                $className,
                $object->getId(),
                $resourceName,
                $row->getRevision(),
                $persistManager,
                $row->getData(),
                $row->getType()
            );
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function getRevisions(string $resourceName, int $resourceId): Collection
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceName);

        if (!$objectAuditManager->getMetadataFactory()->isAudited($className)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        try {
            $revisions = $objectAuditManager->getRevisions($className, $resourceId);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        }

        return $revisions;
    }

    /**
     * {@inheritdoc}
     */
    public function getHistory(string $resourceName, int $resourceId, array $options = array()): array
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceName);

        if (!$objectAuditManager->getMetadataFactory()->isAudited($className)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        try {
            $revisions = $objectAuditManager->getHistory($className, $resourceId);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        } catch (ObjectAuditNotFoundException $exception) {
            throw ResourceAuditNotFoundException::forObjectNotFoundException($exception, $resourceName);
        }

        return $revisions;
    }

    /**
     * {@inheritdoc}
     */
    public function getInitRevision(string $resourceName, int $resourceId)
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceName);

        if (!$objectAuditManager->getMetadataFactory()->isAudited($className)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        try {
            $revision = $objectAuditManager->getInitRevision($className, $resourceId);
        } catch (ObjectAuditNotFoundException $exception) {
            throw ResourceAuditNotFoundException::forObjectNotFoundException($exception, $resourceName);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        }

        return $revision;
    }

    /**
     * {@inheritdoc}
     */
    public function getRevision(string $resourceName, int $resourceId)
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceName);

        if (!$objectAuditManager->getMetadataFactory()->isAudited($className)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        try {
            $revision = $objectAuditManager->getRevision($className, $resourceId);
        } catch (ObjectAuditNotFoundException $exception) {
            throw ResourceAuditNotFoundException::forObjectNotFoundException($exception, $resourceName);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        }

        return $revision;
    }

    /**
     * {@inheritdoc}
     */
    public function saveAudit(ResourceAudit $resourceAudit)
    {
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceAudit->getResourceName());

        return $objectAuditManager->saveAudit($resourceAudit);
    }

    /**
     * {@inheritdoc}
     */
    public function diffRevisions(string $resourceName, int $resourceId, RevisionInterface $oldRevision, RevisionInterface $newRevision): array
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceName);

        if (!$objectAuditManager->getMetadataFactory()->isAudited($className)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        return $objectAuditManager->diffRevisions($className, $resourceId, $oldRevision, $newRevision);
    }

    /**
     * {@inheritdoc}
     */
    public function getValues(ResourceInterface $resource): array
    {
        $className = get_class($resource);
        $resourceName = $this->resourceRegistry->getByClass($className)->getName();
        $objectAuditManager = $this->getResourceObjectAuditManager($resourceName);

        return $objectAuditManager->getValues($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFactory(): ResourceAuditMetadataFactory
    {
        return $this->resourceAuditMetadataFactory;
    }

    /**
     * @param string $resourceName
     *
     * @return string
     */
    private function getResourceModelClass(string $resourceName): string
    {
        return $this->resourceRegistry->get($resourceName)->getClass('model');
    }

    /**
     * @param string $resourceName
     * @throws NotDefinedException
     * @return ObjectAuditManagerInterface
     */
    private function getResourceObjectAuditManager(string $resourceName): ObjectAuditManagerInterface
    {
        $serviceId = $this->resourceRegistry->get($resourceName)->getServiceId('manager');
        /** @var ObjectManager $persistManager */
        $persistManager = $this->container->get($serviceId);

        return $this->objectAuditRegistry->getByPersistManager($persistManager);
    }
}
