<?php

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
     * @param ObjectManager $persistManager
     * @param DriverInterface $driver
     */
    public function __construct(ObjectManager $persistManager, DriverInterface $driver)
    {
        $this->persistManager = $persistManager;
        $this->driver = $driver;
    }

    /**
     * @param string $class
     * @return bool
     */
    public function isClassAudited(string $class)
    {
        $this->load();

        return array_key_exists($class, $this->objectAuditMetadatas);
    }

    /**
     * @param string $class
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

    /**
     *
     */
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