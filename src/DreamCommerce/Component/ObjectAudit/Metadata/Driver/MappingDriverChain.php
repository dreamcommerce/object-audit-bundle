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

declare(strict_types=1);

namespace DreamCommerce\Component\ObjectAudit\Metadata\Driver;

use Doctrine\Persistence\Mapping\MappingException;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadata;

class MappingDriverChain implements DriverInterface
{
    /**
     * The default driver.
     *
     * @var DriverInterface|null
     */
    private $defaultDriver;

    /**
     * @var array
     */
    private $drivers = array();

    /**
     * Gets the default driver.
     *
     * @return DriverInterface|null
     */
    public function getDefaultDriver(): ?DriverInterface
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default driver.
     *
     * @param DriverInterface|null $driver
     */
    public function setDefaultDriver(DriverInterface $driver = null): void
    {
        $this->defaultDriver = $driver;
    }

    /**
     * Adds a nested driver.
     *
     * @param DriverInterface $nestedDriver
     * @param string $namespace
     *
     * @return void
     */
    public function addDriver(DriverInterface $nestedDriver, string $namespace)
    {
        $this->drivers[$namespace] = $nestedDriver;
    }

    /**
     * Gets the array of nested drivers.
     *
     * @return array $drivers
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * {@inheritDoc}
     */
    public function loadMetadataForClass(string $className, ObjectAuditMetadata $objectAuditMetadata): void
    {
        /* @var $driver DriverInterface */
        foreach ($this->drivers as $namespace => $driver) {
            if (strpos($className, $namespace) === 0) {
                $driver->loadMetadataForClass($className, $objectAuditMetadata);
                return;
            }
        }

        if (null !== $this->defaultDriver) {
            $this->defaultDriver->loadMetadataForClass($className, $objectAuditMetadata);
            return;
        }

        throw MappingException::classNotFoundInNamespaces($className, array_keys($this->drivers));
    }

    /**
     * {@inheritDoc}
     */
    public function isTransient(string $className, DriverInterface $parentDriver = null): bool
    {
        /* @var $driver DriverInterface */
        foreach ($this->drivers as $namespace => $driver) {
            if (strpos($className, $namespace) === 0) {
                return $driver->isTransient($className, $this);
            }
        }

        if ($this->defaultDriver !== null) {
            return $this->defaultDriver->isTransient($className);
        }

        return true;
    }
}
