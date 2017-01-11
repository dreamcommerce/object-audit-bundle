<?php

namespace DreamCommerce\Component\ObjectAudit\Exception;

use DreamCommerce\Component\ObjectAudit\Exception\Traits\ResourceTrait;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

class ResourceNotFoundException extends ObjectNotFoundException
{
    const CODE_RESOURCE_NOT_EXIST_AT_SPECIFIC_REVISION = 15;

    use ResourceTrait;

    /**
     * @param string $resourceName
     * @param string $className
     * @param mixed $id
     * @param RevisionInterface $revision
     * @return ResourceNotFoundException
     */
    public static function forResourceAtSpecificRevision(string $resourceName, string $className, $id, RevisionInterface $revision): ResourceNotFoundException
    {
        $message = sprintf(
            "No revision of resource '%s' (%s) was found at revision %s or before. The entity did not exist at the specified revision yet.",
            $resourceName,
            is_array($id) ? implode(', ', $id) : $id,
            $revision->getId()
        );

        $exception = new self($message, self::CODE_RESOURCE_NOT_EXIST_AT_SPECIFIC_REVISION);
        $exception->setResourceName($resourceName)
            ->setClassName($className)
            ->setId($id)
            ->setRevision($revision);

        return $exception;
    }
}
