<?php

namespace DreamCommerce\Component\ObjectAudit\Metadata\Driver;

use Doctrine\Common\Persistence\Mapping\Driver\FileLocator;

abstract class FileDriver implements DriverInterface
{
    /**
     * @var FileLocator
     */
    protected $locator;

    /**
     * @param FileLocator $locator
     */
    public function setLocator(FileLocator $locator)
    {
        $this->locator = $locator;
    }

    /**
     * Loads a mapping file with the given name and returns a map
     * from class/entity names to their corresponding elements.
     *
     * @param string $file The mapping file to load.
     *
     * @return array
     */
    abstract protected function loadMappingFile(string $file): array;

    /**
     * Tries to get a mapping for a given class
     *
     * @param string $className
     *
     * @return null|array|object
     */
    protected function _getMapping(string $className)
    {
        $mapping = $this->loadMappingFile($this->locator->findMappingFile($className));
        if(!isset($mapping[$className])) {
            return null;
        }

        return $mapping[$className];
    }
}