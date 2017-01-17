<?php

/*
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

class AuditedCollectionException extends AuditException
{
    const CODE_COLLECTION_IS_READ_ONLY = 40;
    const CODE_NOT_DEFINED_OFFSET = 41;

    /**
     * @var string
     */
    private $className;

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @param string $className
     * @return $this
     */
    public function setClassName(string $className)
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @param string $className
     * @return AuditedCollectionException
     */
    public static function readOnly(string $className): AuditedCollectionException
    {
        $exception = new self('Collection "' . $className . '" is read-only', self::CODE_COLLECTION_IS_READ_ONLY);
        $exception->setClassName($className);

        return $exception;
    }

    /**
     * @param string $className
     * @param int $offset
     * @return AuditedCollectionException
     */
    public static function forNotDefinedOffset(string $className, int $offset): AuditedCollectionException
    {
        $exception = new self('Offset "' . $offset . '" is not defined', self::CODE_NOT_DEFINED_OFFSET);
        $exception->setClassName($className);

        return $exception;
    }
}
