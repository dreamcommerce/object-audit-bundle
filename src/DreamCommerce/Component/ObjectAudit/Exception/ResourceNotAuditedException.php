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

use DreamCommerce\Component\ObjectAudit\Exception\Traits\ResourceTrait;

class ResourceNotAuditedException extends ObjectNotAuditedException
{
    const CODE_RESOURCE_IS_NOT_AUDITED = 35;

    use ResourceTrait;

    /**
     * @param string $resourceName
     * @param string $className
     *
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
