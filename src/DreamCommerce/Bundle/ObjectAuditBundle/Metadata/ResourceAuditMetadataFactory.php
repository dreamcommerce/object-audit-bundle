<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Metadata;

use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Metadata\ResourceAuditMetadata;
use DreamCommerce\Component\ObjectAudit\ObjectAuditRegistry;
use Sylius\Component\Resource\Metadata\RegistryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ResourceAuditMetadataFactory
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ObjectAuditRegistry
     */
    private $objectAuditRegistry;

    /**
     * @var ResourceAuditMetadata[]
     */
    private $resourceAuditMetadatas = array();

    /**
     * @var RegistryInterface
     */
    private $resourceRegistry;

    /**
     * @var bool
     */
    private $loaded = false;

    /**
     * @param ObjectAuditRegistry $objectAuditRegistry
     * @param ContainerInterface $container
     */
    public function __construct(ObjectAuditRegistry $objectAuditRegistry, ContainerInterface $container)
    {
        $this->objectAuditRegistry = $objectAuditRegistry;
        $this->container = $container;
    }

    /**
     * @param string $class
     * @return bool
     */
    public function isAudited($class)
    {
        $this->load();

        return array_key_exists($class, $this->resourceAuditMetadatas);
    }

    /**
     * @param string $resourceName
     * @return ResourceAuditMetadata
     */
    public function getMetadataFor(string $resourceName)
    {
        $this->load();

        return $this->resourceAuditMetadatas[$resourceName];
    }

    /**
     * @return string[]
     */
    public function getAllResourceNames()
    {
        $this->load();

        return array_keys($this->resourceAuditMetadatas);
    }

    /**
     *
     */
    private function load()
    {
        if ($this->loaded) {
            return;
        }

        foreach($this->resourceRegistry->getAll() as $resourceMetadata) {
            $serviceId = $resourceMetadata->getServiceId('manager');
            $className = $resourceMetadata->getClass('model');
            /** @var ObjectManager $persistManager */
            $persistManager = $this->container->get($serviceId);
            $objectAuditManager = $this->objectAuditRegistry->getByPersistManager($persistManager);
            $objectAuditMetadataFactory = $objectAuditManager->getObjectAuditMetadataFactory();
            if($objectAuditMetadataFactory->isClassAudited($className)) {
                $this->resourceAuditMetadatas[] = $resourceMetadata->getName();
            }
        }

        $this->loaded = true;
    }
}