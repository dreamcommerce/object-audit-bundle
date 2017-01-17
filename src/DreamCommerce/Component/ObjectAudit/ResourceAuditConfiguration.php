<?php

/*
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
    protected $auditedResources = array();

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
