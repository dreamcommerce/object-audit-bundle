<?php

namespace DreamCommerce\Component\ObjectAudit;

use Sylius\Component\Resource\Metadata\RegistryInterface as SyliusRegistryInterface;

class ResourceAuditConfiguration
{
    /**
     * @var ObjectAuditConfiguration
     */
    protected $objectAuditConfiguration;

    /**
     * @var SyliusRegistryInterface
     */
    protected $resourceRegistry;

    /**
     * @var array
     */
    protected $auditedResources = [];

    /**
     * @param ObjectAuditConfiguration $objectAuditConfiguration
     * @param SyliusRegistryInterface  $resourceRegistry
     */
    public function __construct(ObjectAuditConfiguration $objectAuditConfiguration,
                                SyliusRegistryInterface $resourceRegistry)
    {
        $this->objectAuditConfiguration = $objectAuditConfiguration;
        $this->resourceRegistry = $resourceRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function addAuditToResource(string $resourceName)
    {
        if (!$this->isResourceAudited($resourceName)) {
            $auditMetadata = $this->resourceRegistry->get($resourceName);

            $modelClass = $auditMetadata->getClass('model');
            $this->objectAuditConfiguration->addAuditedClass($modelClass);
            $modelInterface = $auditMetadata->getClass('interface');
            $this->objectAuditConfiguration->addAuditedClass($modelInterface);

            $this->auditedResources[] = $resourceName;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeAuditFromResource(string $resourceName)
    {
        if ($this->isResourceAudited($resourceName)) {
            $pos = array_search($resourceName, $this->auditedResources);
            unset($this->auditedResources[$pos]);

            $auditMetadata = $this->resourceRegistry->get($resourceName);

            $modelClass = $auditMetadata->getClass('model');
            $this->objectAuditConfiguration->removeAuditedClass($modelClass);
            $modelInterface = $auditMetadata->getClass('interface');
            $this->objectAuditConfiguration->removeAuditedClass($modelInterface);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isResourceAudited(string $resourceName): bool
    {
        return in_array($resourceName, $this->auditedResources);
    }

    /**
     * @param array $auditedResources
     *
     * @return $this
     */
    public function setAuditedResources(array $auditedResources)
    {
        foreach ($auditedResources as $auditedResource) {
            $this->addAuditToResource($auditedResource);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuditedResources(): array
    {
        return $this->auditedResources;
    }
}
