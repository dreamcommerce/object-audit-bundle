<?php

namespace DreamCommerce\Component\ObjectAudit\Exception;

use DreamCommerce\Component\ObjectAudit\Exception\Traits\ResourceTrait;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

class ResourceNotFoundException extends ObjectNotFoundException
{
    const CODE_RESOURCE_NOT_EXIST_AT_SPECIFIC_REVISION = 15;
    const CODE_RESOURCE_NOT_EXIST_FOR_IDENTIFIER = 16;

    use ResourceTrait;

    /**
     * @param string            $resourceName
     * @param string            $className
     * @param int               $id
     * @param RevisionInterface $revision
     *
     * @return ResourceNotFoundException
     */
    public static function forResourceAtSpecificRevision(string $resourceName, string $className, int $id, RevisionInterface $revision): ResourceNotFoundException
    {
        $message = sprintf(
            "No revision of resource '%s' (%s) was found at revision %s or before. The entity did not exist at the specified revision yet.",
            $resourceName,
            $id,
            $revision->getId()
        );

        $exception = new self($message, self::CODE_RESOURCE_NOT_EXIST_AT_SPECIFIC_REVISION);
        $exception->setResourceName($resourceName)
            ->setClassName($className)
            ->setId($id)
            ->setRevision($revision);

        return $exception;
    }

    /**
     * @param string $resourceName
     * @param string $className
     * @param int    $id
     *
     * @return ResourceNotFoundException
     */
    public static function forResourceIdentifier(string $resourceName, string $className, int $id): ResourceNotFoundException
    {
        $message = sprintf(
            "Resource '%s' (%s) does not exist for identifier (%s)",
            $resourceName,
            $className,
            $id
        );

        $exception = new self($message, self::CODE_RESOURCE_NOT_EXIST_FOR_IDENTIFIER);
        $exception->setResourceName($resourceName)
            ->setClassName($className)
            ->setId($id);

        return $exception;
    }

    /**
     * @param ObjectNotFoundException $exception
     * @param string                  $resourceName
     *
     * @return ResourceNotFoundException
     */
    public static function forObjectNotFoundException(ObjectNotFoundException $exception, string $resourceName): ResourceNotFoundException
    {
        $id = $exception->getId();
        if (is_array($id)) {
            $id = (int) current($id);
        }

        if ($exception->getCode() == static::CODE_OBJECT_NOT_EXIST_AT_SPECIFIC_REVISION) {
            return self::forResourceAtSpecificRevision(
                $resourceName,
                $exception->getClassName(),
                $id,
                $exception->getRevision()
            );
        } elseif ($exception->getCode() == static::CODE_OBJECT_NOT_EXIST_FOR_IDENTIFIERS) {
            return self::forResourceIdentifier(
                $resourceName,
                $exception->getClassName(),
                $id
            );
        }
    }
}
