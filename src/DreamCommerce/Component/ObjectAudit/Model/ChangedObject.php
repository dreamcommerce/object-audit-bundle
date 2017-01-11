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

namespace DreamCommerce\Component\ObjectAudit\Model;

use Webmozart\Assert\Assert;

class ChangedObject
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
     * @var array
     */
    private $revisionData = [];

    /**
     * @var string
     */
    private $revisionType;

    /**
     * @param $object
     * @param RevisionInterface $revision
     * @param array $revisionData
     * @param string $revisionType
     */
    public function __construct($object, RevisionInterface $revision, array $revisionData = [], string $revisionType)
    {
        Assert::oneOf($revisionType, [RevisionInterface::ACTION_INSERT, RevisionInterface::ACTION_UPDATE, RevisionInterface::ACTION_DELETE]);

        $this->object = $object;
        $this->revision = $revision;
        $this->revisionData = $revisionData;
        $this->revisionType = $revisionType;
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
     * @return array
     */
    public function getRevisionData(): array
    {
        return $this->revisionData;
    }

    /**
     * @return string
     */
    public function getRevisionType(): string
    {
        return $this->revisionType;
    }
}
