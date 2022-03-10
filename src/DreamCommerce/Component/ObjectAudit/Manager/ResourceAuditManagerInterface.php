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

declare(strict_types=1);

namespace DreamCommerce\Component\ObjectAudit\Manager;

use Doctrine\\Collections\Collection;
use DreamCommerce\Bundle\ObjectAuditBundle\Metadata\ResourceAuditMetadataFactory;
use DreamCommerce\Component\ObjectAudit\Exception\ResourceAuditNotFoundException;
use DreamCommerce\Component\ObjectAudit\Exception\ResourceDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ResourceNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Model\ResourceAudit;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface ResourceAuditManagerInterface
{
    /**
     * Find a resource at the specific revision.
     *
     * @param string            $resourceName
     * @param int               $resourceId
     * @param RevisionInterface $revision
     * @param array             $options
     *
     * @throws ResourceDeletedException
     * @throws ResourceAuditNotFoundException
     * @throws ResourceNotAuditedException
     *
     * @return ResourceInterface
     */
    public function find(string $resourceName, int $resourceId, RevisionInterface $revision, array $options = array()): ResourceInterface;

    /**
     * @param string            $resourceName
     * @param array             $fields
     * @param RevisionInterface $revision
     * @param array             $options
     *
     * @return ResourceInterface[]
     */
    public function findByFieldsAndRevision(string $resourceName, array $fields, RevisionInterface $revision, array $options = array()): array;

    /**
     * @param string            $resourceName
     * @param RevisionInterface $revision
     * @param array             $options
     *
     * @throws ResourceNotAuditedException
     *
     * @return ResourceAudit[]
     */
    public function findChangesAtRevision(string $resourceName, RevisionInterface $revision, array $options = array()): array;

    /**
     * @param RevisionInterface $revision
     * @param array             $options
     *
     * @return ResourceAudit[]
     */
    public function findAllChangesAtRevision(RevisionInterface $revision, array $options = array()): array;

    /**
     * Find all revisions that were made of resource with given id.
     *
     * @param string $resourceName
     * @param int    $resourceId
     *
     * @throws ResourceNotAuditedException
     *
     * @return Collection|RevisionInterface[]
     */
    public function getRevisions(string $resourceName, int $resourceId): Collection;

    /**
     * @param string $resourceName
     * @param int    $resourceId
     * @param array  $options
     *
     * @throws ResourceAuditNotFoundException
     * @throws ResourceNotAuditedException
     *
     * @return ResourceAudit[]
     */
    public function getHistory(string $resourceName, int $resourceId, array $options = array()): array;

    /**
     * Gets the initialize revision of the resource with given ID.
     *
     * @param string $resourceName
     * @param int    $resourceId
     *
     * @throws ResourceNotAuditedException
     * @throws ResourceAuditNotFoundException
     *
     * @return RevisionInterface|null
     */
    public function getInitRevision(string $resourceName, int $resourceId): ?RevisionInterface;

    /**
     * Gets the current revision of the resource with given ID.
     *
     * @param string $resourceName
     * @param int    $resourceId
     *
     * @throws ResourceNotAuditedException
     * @throws ResourceAuditNotFoundException
     *
     * @return RevisionInterface|null
     */
    public function getRevision(string $resourceName, int $resourceId): ?RevisionInterface;

    /**
     * @param ResourceAudit $resourceAudit
     *
     * @throws ResourceNotAuditedException
     */
    public function saveAudit(ResourceAudit $resourceAudit): void;

    /**
     * Get an array with the differences of between two specific revisions of
     * an object with a given id.
     *
     * @param string            $resourceName
     * @param int               $resourceId
     * @param RevisionInterface $oldRevision
     * @param RevisionInterface $newRevision
     *
     * @throws ResourceNotAuditedException
     *
     * @return array
     */
    public function diffRevisions(string $resourceName, int $resourceId, RevisionInterface $oldRevision, RevisionInterface $newRevision): array;

    /**
     * Get the values for a specific entity as an associative array.
     *
     * @param ResourceInterface $resource
     *
     * @return array
     */
    public function getValues(ResourceInterface $resource): array;

    /**
     * @return ResourceAuditMetadataFactory
     */
    public function getMetadataFactory(): ResourceAuditMetadataFactory;
}
