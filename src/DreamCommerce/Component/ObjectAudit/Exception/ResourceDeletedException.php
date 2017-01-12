<?php

namespace DreamCommerce\Component\ObjectAudit\Exception;

use DreamCommerce\Component\ObjectAudit\Exception\Traits\ResourceTrait;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

class ResourceDeletedException extends ObjectDeletedException
{
    const CODE_RESOURCE_NOT_EXIST_AT_SPECIFIC_REVISION = 25;

    use ResourceTrait;

    /**
     * @param string            $resourceName
     * @param string            $className
     * @param mixed             $id
     * @param RevisionInterface $revision
     *
     * @return ResourceDeletedException
     */
    public static function forResourceAtSpecificRevision(string $resourceName, string $className, $id, RevisionInterface $revision): ResourceDeletedException
    {
        $message = sprintf(
            'Resource "%s" entity id "%s" has been removed at revision %s',
            $className,
            is_array($id) ? implode(', ', $id) : $id,
            $revision->getId()
        );

        $exception = new self($message, self::CODE_OBJECT_HAS_BEEN_REMOVED_AT_SPECIFIC_REVISION);
        $exception->setResourceName($resourceName)
            ->setClassName($className)
            ->setId($id)
            ->setRevision($revision);

        return $exception;
    }

    /**
     * @param ObjectDeletedException $exception
     * @param string                 $resourceName
     *
     * @throws ResourceDeletedException
     *
     * @return ResourceDeletedException
     */
    public static function forObjectDeletedException(ObjectDeletedException $exception, string $resourceName): ResourceDeletedException
    {
        $id = $exception->getId();
        if (is_array($id)) {
            $id = (int) current($id);
        }

        return self::forResourceAtSpecificRevision(
            $resourceName,
            $exception->getClassName(),
            $id,
            $exception->getRevision()
        );
    }
}
