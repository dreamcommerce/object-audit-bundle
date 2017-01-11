<?php
/*
 * (c) 2011 SimpleThings GmbH
 *
 * @package DreamCommerce\Component\ObjectAudit
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

use DreamCommerce\Component\ObjectAudit\Exception\Traits\ObjectTrait;
use DreamCommerce\Component\ObjectAudit\Exception\Traits\RevisionTrait;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

class ObjectNotFoundException extends AuditException
{
    const CODE_OBJECT_NOT_EXIST_AT_SPECIFIC_REVISION = 10;

    use ObjectTrait;
    use RevisionTrait;

    /**
     * @param string $className
     * @param mixed $id
     * @param RevisionInterface $revision
     * @return ObjectNotFoundException
     */
    public static function forObjectAtSpecificRevision(string $className, $id, RevisionInterface $revision): ObjectNotFoundException
    {
        $message = sprintf(
            "No revision of class '%s' (%s) was found at revision %s or before. The entity did not exist at the specified revision yet.",
            $className,
            is_array($id) ? implode(', ', $id) : $id,
            $revision->getId()
        );

        $exception = new self($message, self::CODE_OBJECT_NOT_EXIST_AT_SPECIFIC_REVISION);
        $exception->setClassName($className)
            ->setId($id)
            ->setRevision($revision);

        return $exception;
    }
}
