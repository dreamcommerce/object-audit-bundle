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

use Sylius\Component\Resource\Model\ResourceInterface;

final class ChangedResource extends ChangedObject
{
    /**
     * @var string
     */
    private $resourceName;

    /**
     * @param ResourceInterface $resource
     * @param string $resourceName
     * @param RevisionInterface $revision
     * @param array $revisionData
     * @param string $revisionType
     */
    public function __construct(ResourceInterface $resource, string $resourceName, RevisionInterface $revision, array $revisionData = [], string $revisionType)
    {
        $this->resourceName = $resourceName;
        parent::__construct($resource, $revision, $revisionData, $revisionType);
    }

    /**
     * @return string
     */
    public function getResourceName(): string
    {
        return $this->resourceName;
    }
}
