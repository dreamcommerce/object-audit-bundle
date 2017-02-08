<?php

/*
 * (c) 2017 DreamCommerce
 *
 * @package DreamCommerce\Component\ObjectAudit
 * @author Michał Korus <michal.korus@dreamcommerce.com>
 * @link https://www.dreamcommerce.com
 *
 * (c) 2011 SimpleThings GmbH
 *
 * @package SimpleThings\EntityAudit
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 * @link http://www.simplethings.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

namespace DreamCommerce\Component\ObjectAudit\Exception;

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

class ResourceAuditNotFoundException extends ResourceException
{
    const CODE_RESOURCE_AUDIT_NOT_EXIST_AT_SPECIFIC_REVISION = 15;
    const CODE_RESOURCE_AUDIT_NOT_EXIST_FOR_IDENTIFIER = 16;

    /**
     * @param string            $resourceName
     * @param string            $className
     * @param int               $id
     * @param RevisionInterface $revision
     *
     * @return ResourceAuditNotFoundException
     */
    public static function forResourceAtSpecificRevision(string $resourceName, string $className, int $id, RevisionInterface $revision): ResourceAuditNotFoundException
    {
        $message = sprintf(
            "No revision of resource '%s' (%s) was found at revision %s or before. The entity did not exist at the specified revision yet.",
            $resourceName,
            $id,
            $revision->getId()
        );

        $exception = new self($message, self::CODE_RESOURCE_AUDIT_NOT_EXIST_AT_SPECIFIC_REVISION);
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
     * @return ResourceAuditNotFoundException
     */
    public static function forResourceIdentifier(string $resourceName, string $className, int $id): ResourceAuditNotFoundException
    {
        $message = sprintf(
            "Resource '%s' (%s) does not exist for identifier (%s)",
            $resourceName,
            $className,
            $id
        );

        $exception = new self($message, self::CODE_RESOURCE_AUDIT_NOT_EXIST_FOR_IDENTIFIER);
        $exception->setResourceName($resourceName)
            ->setClassName($className)
            ->setId($id);

        return $exception;
    }

    /**
     * @param ObjectAuditNotFoundException $exception
     * @param string                  $resourceName
     *
     * @return ResourceAuditNotFoundException
     */
    public static function forObjectNotFoundException(ObjectAuditNotFoundException $exception, string $resourceName): ResourceAuditNotFoundException
    {
        $id = $exception->getId();
        if (is_array($id)) {
            $id = (int) current($id);
        }

        if ($exception->getCode() == ObjectAuditNotFoundException::CODE_OBJECT_AUDIT_NOT_EXIST_AT_SPECIFIC_REVISION) {
            return self::forResourceAtSpecificRevision(
                $resourceName,
                $exception->getClassName(),
                $id,
                $exception->getRevision()
            );
        } elseif ($exception->getCode() == ObjectAuditNotFoundException::CODE_OBJECT_AUDIT_NOT_EXIST_FOR_IDENTIFIERS) {
            return self::forResourceIdentifier(
                $resourceName,
                $exception->getClassName(),
                $id
            );
        }
    }
}
