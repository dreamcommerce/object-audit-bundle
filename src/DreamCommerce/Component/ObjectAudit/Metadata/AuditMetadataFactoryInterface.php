<?php

namespace DreamCommerce\Component\ObjectAudit\Metadata;

interface AuditMetadataFactoryInterface
{
    /**
     * @param string $name
     *
     * @return bool
     */
    public function isAudited(string $name): bool;

    /**
     * @param string $name
     *
     * @return object|null
     */
    public function getMetadataFor(string $name);

    /**
     * @return string[]
     */
    public function getAllNames(): array;
}