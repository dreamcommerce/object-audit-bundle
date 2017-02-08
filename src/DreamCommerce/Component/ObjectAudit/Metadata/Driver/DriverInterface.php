<?php

namespace DreamCommerce\Component\ObjectAudit\Metadata\Driver;

use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadata;

/**
 * @author David Badura <d.a.badura@gmail.com>
 */
interface DriverInterface
{
    /**
     * @param string              $class
     * @param ObjectAuditMetadata $objectAuditMetadata
     */
    public function loadMetadataForClass($class, ObjectAuditMetadata $objectAuditMetadata);
    /**
     * @param string $class
     *
     * @return bool
     */
    public function isTransient($class);
}
