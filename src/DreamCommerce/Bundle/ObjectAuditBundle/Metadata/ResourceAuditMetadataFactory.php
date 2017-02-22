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
     * @param ContainerInterface  $container
     */
    public function __construct(ObjectAuditRegistry $objectAuditRegistry, ContainerInterface $container)
    {
        $this->objectAuditRegistry = $objectAuditRegistry;
        $this->container = $container;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function isAudited($class)
    {
        $this->load();

        return array_key_exists($class, $this->resourceAuditMetadatas);
    }

    /**
     * @param string $resourceName
     *
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
            $objectAuditMetadataFactory = $objectAuditManager->getObjectAuditMetadataFactory();
            if ($objectAuditMetadataFactory->isClassAudited($className)) {
                $this->resourceAuditMetadatas[] = $resourceMetadata->getName();
            }
        }

        $this->loaded = true;
    }
}