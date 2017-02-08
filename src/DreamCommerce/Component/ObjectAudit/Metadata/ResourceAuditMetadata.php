<?php

namespace DreamCommerce\Component\ObjectAudit\Metadata;

use Doctrine\ORM\Mapping\ClassMetadata;

class ResourceAuditMetadata extends ObjectAuditMetadata
{
    public $resourceName;

    /**
     * @param string        $resourceName
     * @param ClassMetadata $classMetadata
     */
    public function __construct(string $resourceName, ClassMetadata $classMetadata)
    {
        $this->resourceName = $resourceName;

        parent::__construct($classMetadata);
    }
}
