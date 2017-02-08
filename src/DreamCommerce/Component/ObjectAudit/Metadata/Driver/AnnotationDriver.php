<?php

namespace DreamCommerce\Component\ObjectAudit\Metadata\Driver;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation\Auditable;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation\Ignore;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadata;

/**
 * @author David Badura <d.a.badura@gmail.com>
 */
class AnnotationDriver implements DriverInterface
{
    /**
     * @var AnnotationReader
     */
    private $reader;

    /**
     * @param AnnotationReader $reader
     */
    public function __construct(AnnotationReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @param string $class
     * @param ObjectAuditMetadata $objectAuditMetadata
     * @return void
     */
    public function loadMetadataForClass($class, ObjectAuditMetadata $objectAuditMetadata)
    {
        $reflection = new \ReflectionClass($class);

        foreach ($reflection->getProperties() as $property) {
            if ($this->reader->getPropertyAnnotation($property, Ignore::class)) {
                $objectAuditMetadata->ignoredProperties[] = $property->name;
            }
        }
    }

    /**
     * @param string $class
     * @return bool
     */
    public function isTransient($class)
    {
        $reflection = new \ReflectionClass($class);

        return (bool)$this->reader->getClassAnnotation($reflection, Auditable::class);
    }

    /**
     * @return AnnotationDriver
     */
    public static function create()
    {
        // use composer autoloader
        AnnotationRegistry::registerLoader('class_exists');
        $reader = new AnnotationReader();
        return new self($reader);
    }
}