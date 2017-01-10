<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Model\ChangedResource;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\ResourceAuditConfiguration;
use DreamCommerce\Component\ObjectAudit\ResourceAuditManagerInterface;
use Sylius\Component\Resource\Metadata\RegistryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ResourceAuditManager implements ResourceAuditManagerInterface
{
    /**
     * @var ObjectAuditManagerInterface
     */
    protected $objectAuditManager;

    /**
     * @var ResourceAuditConfiguration
     */
    protected $configuration;

    /**
     * @var RegistryInterface
     */
    protected $resourceRegistry;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ObjectAuditManagerInterface $objectAuditManager
     * @param ResourceAuditConfiguration  $configuration
     * @param RegistryInterface           $resourceRegistry
     * @param ContainerInterface          $container
     */
    public function __construct(ObjectAuditManagerInterface $objectAuditManager,
                                ResourceAuditConfiguration $configuration,
                                RegistryInterface $resourceRegistry,
                                ContainerInterface $container
    ) {
        $this->objectAuditManager = $objectAuditManager;
        $this->configuration = $configuration;
        $this->resourceRegistry = $resourceRegistry;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function findResourceByRevision(string $resourceName, int $resourceId, RevisionInterface $revision, array $options = [])
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        return $this->objectAuditManager->findObjectByRevision($className, $resourceId, $revision, $objectManager, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllResourcesChangedAtRevision(RevisionInterface $revision, array $options = []): array
    {
        $result = [];
        foreach ($this->configuration->getAuditedResources() as $auditedResource) {
            $result = array_merge(
                $result,
                $this->findResourcesChangedAtRevision($auditedResource, $revision, $options)
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findResourcesChangedAtRevision(string $resourceName, RevisionInterface $revision, array $options = []): array
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        $rows = $this->objectAuditManager->findObjectsChangedAtRevision($className, $revision, $objectManager, $options);
        foreach ($rows as $k => $row) {
            /** @var ResourceInterface $object */
            $object = $row->getObject();
            $revisionType = $row->getRevisionType();

            $rows[$k] = new ChangedResource($object, $resourceName, $revisionType);
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function findResourceRevisions(string $resourceName, int $resourceId): Collection
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        return $this->objectAuditManager->findObjectRevisions($className, $resourceId, $objectManager);
    }

    /**
     * {@inheritdoc}
     */
    public function getInitializeResourceRevision(string $resourceName, int $resourceId)
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        return $this->objectAuditManager->getInitializeObjectRevision($className, $resourceId, $objectManager);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentResourceRevision(string $resourceName, int $resourceId)
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        return $this->objectAuditManager->getCurrentObjectRevision($className, $resourceId, $objectManager);
    }

    /**
     * {@inheritdoc}
     */
    public function saveResourceRevisionData(string $resourceName, int $resourceId, RevisionInterface $revision, string $revisionType, array $resourceData = array())
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        return $this->objectAuditManager->saveObjectRevisionData($className, $resourceId, $revision, $revisionType, $resourceData, $objectManager);
    }

    /**
     * {@inheritdoc}
     */
    public function diffResourceRevisions(string $resourceName, int $resourceId, RevisionInterface $oldRevision, RevisionInterface $newRevision): array
    {
        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        return $this->objectAuditManager->diffObjectRevisions($className, $resourceId, $oldRevision, $newRevision, $objectManager);
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceValues(ResourceInterface $resource): array
    {
        $className = get_class($resource);
        $serviceId = $this->resourceRegistry->getByClass($className)->getServiceId('manager');

        return $this->objectAuditManager->getObjectValues($resource, $this->container->get($serviceId));
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectAuditManager(): ObjectAuditManagerInterface
    {
        return $this->objectAuditManager;
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
     *
     * @return ObjectManager
     */
    private function getResourceObjectManager(string $resourceName): ObjectManager
    {
        $serviceId = $this->resourceRegistry->get($resourceName)->getServiceId('manager');
        /** @var ObjectManager $objectManager */
        $objectManager = $this->container->get($serviceId);

        return $objectManager;
    }
}
