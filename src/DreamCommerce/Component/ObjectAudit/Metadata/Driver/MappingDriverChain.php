<?php

namespace DreamCommerce\Component\ObjectAudit\Metadata\Driver;

use Doctrine\Common\Persistence\Mapping\MappingException;
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
    public function getDefaultDriver()
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default driver.
     *
     * @param DriverInterface $driver
     *
     * @return void
     */
    public function setDefaultDriver(DriverInterface $driver)
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
    public function addDriver(DriverInterface $nestedDriver, $namespace)
    {
        $this->drivers[$namespace] = $nestedDriver;
    }

    /**
     * Gets the array of nested drivers.
     *
     * @return array $drivers
     */
    public function getDrivers()
    {
        return $this->drivers;
    }

    /**
     * {@inheritDoc}
     */
    public function loadMetadataForClass(string $className, ObjectAuditMetadata $objectAuditMetadata)
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
    public function isTransient(string $className): bool
    {
        /* @var $driver DriverInterface */
        foreach ($this->drivers AS $namespace => $driver) {
            if (strpos($className, $namespace) === 0) {
                return $driver->isTransient($className);
            }
        }

        if ($this->defaultDriver !== null) {
            return $this->defaultDriver->isTransient($className);
        }

        return true;
    }
}