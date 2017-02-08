<?php

namespace DreamCommerce\Component\ObjectAudit\Metadata;

use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @author David Badura <d.a.badura@gmail.com>
 */
class ObjectAuditMetadata
{
    /**
     * @var string
     */
    public $className;

    /**
     * @var ClassMetadata
     */
    public $classMetadata;

    /**
     * @var string[]
     */
    public $ignoredProperties = array();

    /**
     * @param ClassMetadata $classMetadata
     */
    public function __construct(ClassMetadata $classMetadata)
    {
        $this->classMetadata = $classMetadata;
        $this->className = $classMetadata->name;
    }
}