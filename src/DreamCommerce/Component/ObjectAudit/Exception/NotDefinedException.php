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

use \DreamCommerce\Component\Common\Exception\NotDefinedException as BaseNotDefinedException;
use Doctrine\Common\Persistence\ObjectManager;

class NotDefinedException extends BaseNotDefinedException
{
    const CODE_OBJECT_AUDIT_MANAGER_NAME = 20;
    const CODE_PERSIST_MANAGER = 21;

    /**
     * @var string
     */
    private $objectAuditManagerName;

    /**
     * @var ObjectManager
     */
    private $persistManager;

    /**
     * @param string $objectAuditManagerName
     * @return NotDefinedException
     */
    public static function forObjectAuditManager(string $objectAuditManagerName): NotDefinedException
    {
        $exception = new static('The object audit manager defined by name does not exist', static::CODE_OBJECT_AUDIT_MANAGER_NAME);
        $exception->objectAuditManagerName = $objectAuditManagerName;

        return $exception;
    }

    /**
     * @param ObjectManager $persistManager
     * @return NotDefinedException
     */
    public static function forPersistManager(ObjectManager $persistManager): NotDefinedException
    {
        $exception = new static('The object audit manager defined by persist manager does not exist', static::CODE_PERSIST_MANAGER);
        $exception->persistManager = $persistManager;

        return $exception;
    }
}
