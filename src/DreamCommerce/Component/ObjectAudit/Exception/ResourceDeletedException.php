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

class ResourceDeletedException extends ResourceException
{
    const CODE_RESOURCE_HAS_BEEN_REMOVED_AT_SPECIFIC_REVISION = 25;

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
        $exception = new self('The resource has been removed', self::CODE_RESOURCE_HAS_BEEN_REMOVED_AT_SPECIFIC_REVISION);
        $exception->resourceName = $resourceName;
        $exception->className = $className;
        $exception->id = $id;
        $exception->revision = $revision;

        return $exception;
    }

    /**
     * @param ObjectAuditDeletedException $exception
     * @param string                      $resourceName
     *
     * @throws ResourceDeletedException
     *
     * @return ResourceDeletedException
     */
    public static function forObjectDeletedException(ObjectAuditDeletedException $exception, string $resourceName): ResourceDeletedException
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
