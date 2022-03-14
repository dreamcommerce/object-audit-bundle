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

class ObjectException extends AuditException
{
    const CODE_SINGLE_INSUFFICIENT_IDENTIFIER = 1;
    const CODE_INCOMPLETE_IDENTIFIERS = 2;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var mixed
     */
    protected $id;

    /**
     * @var RevisionInterface
     */
    protected $revision;

    /**
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * @return mixed|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return RevisionInterface|null
     */
    public function getRevision(): ?RevisionInterface
    {
        return $this->revision;
    }

    /**
     * @param mixed $objectIds
     * @return ObjectException
     */
    public static function forSingleInsufficientIdentifier($objectIds): ObjectException
    {
        $exception = new static('The class is defined by more identifiers than 1', static::CODE_SINGLE_INSUFFICIENT_IDENTIFIER);
        $exception->id = $objectIds;

        return $exception;
    }

    /**
     * @param mixed $objectIds
     * @return ObjectException
     */
    public static function forIncompleteIdentifiers($objectIds): ObjectException
    {
        $exception = new static('List of field identifiers is incomplete', static::CODE_INCOMPLETE_IDENTIFIERS);
        $exception->id = $objectIds;

        return $exception;
    }
}
