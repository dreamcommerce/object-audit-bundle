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

declare(strict_types=1);

namespace DreamCommerce\Component\ObjectAudit\Exception;

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

class ObjectAuditNotFoundException extends ObjectException
{
    const CODE_OBJECT_AUDIT_NOT_EXIST_AT_SPECIFIC_REVISION = 10;
    const CODE_OBJECT_AUDIT_NOT_EXIST_FOR_IDENTIFIERS = 11;

    /**
     * @param string            $className
     * @param mixed             $id
     * @param RevisionInterface $revision
     *
     * @return ObjectAuditNotFoundException
     */
    public static function forObjectAtSpecificRevision(string $className, $id, RevisionInterface $revision): ObjectAuditNotFoundException
    {
        $exception = new self('The object did not exist at the specified revision', self::CODE_OBJECT_AUDIT_NOT_EXIST_AT_SPECIFIC_REVISION);
        $exception->className = $className;
        $exception->id = $id;
        $exception->revision = $revision;

        return $exception;
    }

    /**
     * @param string $className
     * @param mixed  $id
     *
     * @return ObjectAuditNotFoundException
     */
    public static function forObjectIdentifiers(string $className, $id): ObjectAuditNotFoundException
    {
        $exception = new self('The object does not exist for specified identifiers', self::CODE_OBJECT_AUDIT_NOT_EXIST_FOR_IDENTIFIERS);
        $exception->className = $className;
        $exception->id = $id;

        return $exception;
    }
}
