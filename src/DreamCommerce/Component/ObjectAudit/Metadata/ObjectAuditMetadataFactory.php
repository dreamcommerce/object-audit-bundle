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

namespace DreamCommerce\Component\ObjectAudit\Metadata;

use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\DriverInterface;

/**
 * @author David Badura <d.a.badura@gmail.com>
 */
final class ObjectAuditMetadataFactory
{
    /**
     * @var ObjectManager
     */
    private $persistManager;

    /**
     * @var DriverInterface
     */
    private $driver;

    /**
     * @var ObjectAuditMetadata[]
     */
    private $objectAuditMetadatas = array();
    /**
     * @var bool
     */
    private $loaded = false;

    /**
     * @param ObjectManager   $persistManager
     * @param DriverInterface $driver
     */
    public function __construct(ObjectManager $persistManager, DriverInterface $driver)
    {
        $this->persistManager = $persistManager;
        $this->driver = $driver;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function isClassAudited(string $class)
    {
        $this->load();

        return array_key_exists($class, $this->objectAuditMetadatas);
    }

    /**
     * @param string $class
     *
     * @return ObjectAuditMetadata
     */
    public function getMetadataForClass(string $class)
    {
        $this->load();

        return $this->objectAuditMetadatas[$class];
    }

    /**
     * @return string[]
     */
    public function getAllClassNames()
    {
        $this->load();

        return array_keys($this->objectAuditMetadatas);
    }

    private function load()
    {
        if ($this->loaded) {
            return;
        }

        $doctrineClassMetadatas = $this->persistManager->getMetadataFactory()->getAllMetadata();

        foreach ($doctrineClassMetadatas as $doctrineClassMetadata) {
            $class = $doctrineClassMetadata->name;

            if (!$this->driver->isTransient($class)) {
                continue;
            }

            $classMetadata = new ObjectAuditMetadata($doctrineClassMetadata);
            $this->driver->loadMetadataForClass($class, $classMetadata);
            $this->objectAuditMetadatas[$class] = $classMetadata;
        }

        $this->loaded = true;
    }
}
