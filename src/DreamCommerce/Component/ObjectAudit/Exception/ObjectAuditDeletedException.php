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

class ObjectAuditDeletedException extends ObjectException
{
    const CODE_OBJECT_HAS_BEEN_REMOVED_AT_SPECIFIC_REVISION = 20;

    /**
     * @param string $className
     * @param $id
     * @param RevisionInterface $revision
     *
     * @return ObjectAuditDeletedException
     */
    public static function forObjectAtSpecificRevision(string $className, $id, RevisionInterface $revision): ObjectAuditDeletedException
    {
        $message = sprintf(
            'Class "%s" entity id "%s" has been removed at revision %s',
            $className,
            is_array($id) ? implode(', ', $id) : $id,
            $revision->getId()
        );

        $exception = new self($message, self::CODE_OBJECT_HAS_BEEN_REMOVED_AT_SPECIFIC_REVISION);
        $exception->setClassName($className)
            ->setId($id)
            ->setRevision($revision);

        return $exception;
    }
}
