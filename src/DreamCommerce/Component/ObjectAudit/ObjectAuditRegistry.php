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

namespace DreamCommerce\Component\ObjectAudit;

use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use SplObjectStorage;

final class ObjectAuditRegistry
{
    /**
     * @var array
     */
    private $objectAuditManagers = array();

    /**
     * @var SplObjectStorage
     */
    private $persistManagers;

    /**
     * @param string                      $name
     * @param ObjectAuditManagerInterface $objectAuditManager
     */
    public function registerObjectAuditManager(string $name, ObjectAuditManagerInterface $objectAuditManager)
    {
        if ($this->persistManagers === null) {
            $this->persistManagers = new SplObjectStorage();
        }
        $persistManager = $objectAuditManager->getPersistManager();
        if (isset($this->objectAuditManagers[$name])) {
            throw new \InvalidArgumentException(); // TODO
        }
        if (isset($this->persistManagers[$persistManager])) {
            throw new \InvalidArgumentException(); // TODO
        }

        $this->objectAuditManagers[$name] = $objectAuditManager;
        $this->persistManagers[$persistManager] = $objectAuditManager;
    }

    /**
     * @param string $name
     *
     * @return ObjectAuditManagerInterface|null
     */
    public function getByName(string $name)
    {
        if (!isset($this->objectAuditManagers[$name])) {
            return null;
        }

        return $this->objectAuditManagers[$name];
    }

    /**
     * @param ObjectManager $persistManager
     *
     * @return null|ObjectAuditManagerInterface
     */
    public function getByPersistManager(ObjectManager $persistManager)
    {
        if (!isset($this->persistManagers[$persistManager])) {
            return null;
        }

        return $this->persistManagers[$persistManager];
    }
}
