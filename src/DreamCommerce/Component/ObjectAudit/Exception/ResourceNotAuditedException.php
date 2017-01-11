<?php

namespace DreamCommerce\Component\ObjectAudit\Exception;

use DreamCommerce\Component\ObjectAudit\Exception\Traits\ResourceTrait;

class ResourceNotAuditedException extends ObjectNotAuditedException
{
    const CODE_RESOURCE_IS_NOT_AUDITED = 35;

    use ResourceTrait;

    /**
     * @param string $resourceName
     * @param string $className
     * @return ResourceNotAuditedException
     */
    public static function forResource(string $resourceName, string $className = null): ResourceNotAuditedException
    {
        $message = sprintf(
            "Resource '$resourceName' is not audited.",
            $className
        );

        $exception = new self($message, self::CODE_RESOURCE_IS_NOT_AUDITED);
        $exception->setClassName($className)
            ->setResourceName($resourceName);

        return $exception;
    }
}