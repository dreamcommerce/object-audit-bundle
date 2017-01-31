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

namespace DreamCommerce\Component\ObjectAudit\Model;

use Doctrine\Common\Persistence\ObjectManager;
use Webmozart\Assert\Assert;

class ChangedObject extends AuditedObject
{
    /**
     * @var array
     */
    private $revisionData = array();

    /**
     * @var string
     */
    private $revisionType;

    /**
     * @param object            $object
     * @param string            $className
     * @param RevisionInterface $revision
     * @param ObjectManager     $objectManager
     * @param array             $revisionData
     * @param string            $revisionType
     */
    public function __construct($object, string $className = null, RevisionInterface $revision,
                                ObjectManager $objectManager, array $revisionData, string $revisionType)
    {
        Assert::oneOf($revisionType, array(RevisionInterface::ACTION_INSERT, RevisionInterface::ACTION_UPDATE, RevisionInterface::ACTION_DELETE));

        $this->revisionData = $revisionData;
        $this->revisionType = $revisionType;

        parent::__construct($object, $className, $revision, $objectManager);
    }

    /**
     * @return array
     */
    public function getRevisionData(): array
    {
        return $this->revisionData;
    }

    /**
     * @param array $revisionData
     * @return $this
     */
    public function setRevisionData(array $revisionData)
    {
        $this->revisionData = $revisionData;

        return $this;
    }

    /**
     * @return string
     */
    public function getRevisionType(): string
    {
        return $this->revisionType;
    }
}
