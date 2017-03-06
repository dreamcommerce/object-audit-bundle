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

namespace DreamCommerce\Bundle\ObjectAuditBundle\Metadata;

use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
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
     * @var ObjectAuditManagerInterface[]
     */
    private $auditResources = array();

    /**
     * @var ResourceAuditMetadata[]
     */
    private $resourceMetadatas = array();

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
     * @param RegistryInterface $resourceRegistry
     * @param ContainerInterface  $container
     */
    public function __construct(ObjectAuditRegistry $objectAuditRegistry,
                                RegistryInterface $resourceRegistry,
                                ContainerInterface $container)
    {
        $this->objectAuditRegistry = $objectAuditRegistry;
        $this->container = $container;
        $this->resourceRegistry = $resourceRegistry;
    }

    /**
     * @param string $resourceName
     *
     * @return bool
     */
    public function isAudited($resourceName)
    {
        $this->load();

        return array_key_exists($resourceName, $this->auditResources);
    }

    /**
     * @param string $resourceName
     *
     * @return ResourceAuditMetadata|null
     */
    public function getMetadataFor(string $resourceName)
    {
        $this->load();
        if (!isset($this->auditResources[$resourceName])) {
            return null;
        }

        if (!isset($this->resourceMetadatas[$resourceName])) {
            $objectAuditManager = $this->auditResources[$resourceName];
            $metadataFactory = $objectAuditManager->getMetadataFactory();
            $className = $this->resourceRegistry->get($resourceName)->getClass('model');
            $objectMetadata = $metadataFactory->getMetadataForClass($className);
            $this->resourceMetadatas[$resourceName] = new ResourceAuditMetadata($resourceName, $objectMetadata);
        }

        return $this->resourceMetadatas[$resourceName];
    }

    /**
     * @return string[]
     */
    public function getAllResourceNames()
    {
        $this->load();

        return array_keys($this->auditResources);
    }

    private function load()
    {
        if ($this->loaded) {
            return;
        }

        foreach ($this->resourceRegistry->getAll() as $resourceMetadata) {
            $serviceId = $resourceMetadata->getServiceId('manager');
            $className = $resourceMetadata->getClass('model');
            /** @var ObjectManager $persistManager */
            $persistManager = $this->container->get($serviceId);
            $objectAuditManager = $this->objectAuditRegistry->getByPersistManager($persistManager);
            $objectAuditMetadataFactory = $objectAuditManager->getMetadataFactory();
            if ($objectAuditMetadataFactory->isClassAudited($className)) {
                $alias = $resourceMetadata->getAlias();
                $this->auditResources[$alias] = $objectAuditManager;
            }
        }

        $this->loaded = true;
    }
}
