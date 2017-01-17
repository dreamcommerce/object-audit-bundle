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

namespace DreamCommerce\Component\ObjectAudit\Model;

use Doctrine\Common\Persistence\ObjectManager;

class AuditedObject
{
    /**
     * @var object
     */
    private $object;

    /**
     * @var RevisionInterface
     */
    private $revision;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @param object $object
     * @param RevisionInterface $revision
     * @param ObjectManager $objectManager
     */
    public function __construct($object, RevisionInterface $revision, ObjectManager $objectManager)
    {
        $this->object = $object;
        $this->revision = $revision;
        $this->objectManager = $objectManager;
    }

    /**
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @return RevisionInterface
     */
    public function getRevision(): RevisionInterface
    {
        return $this->revision;
    }

    /**
     * @return ObjectManager
     */
    public function getObjectManager(): ObjectManager
    {
        return $this->objectManager;
    }
}