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

namespace DreamCommerce\Component\ObjectAudit\Manager;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Configuration\BaseAuditConfiguration;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectAuditDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectAuditNotFoundException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadataFactory;
use DreamCommerce\Component\ObjectAudit\Model\ObjectAudit;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

interface ObjectAuditManagerInterface
{
    const DRIVER_ORM = 'orm';

    /**
     * Find an object at the specific revision.
     *
     * @param string            $className
     * @param mixed             $objectIds
     * @param RevisionInterface $revision
     * @param array             $options
     *
     * @throws ObjectAuditDeletedException
     * @throws ObjectAuditNotFoundException
     * @throws ObjectNotAuditedException
     *
     * @return object
     */
    public function find(string $className, $objectIds, RevisionInterface $revision, array $options = array());

    /**
     * @param string            $className
     * @param array             $fields
     * @param string|null       $indexBy
     * @param RevisionInterface $revision
     * @param array             $options
     *
     * @throws ObjectNotAuditedException
     *
     * @return array
     */
    public function findByFieldsAndRevision(string $className, array $fields, string $indexBy = null, RevisionInterface $revision, array $options = array()): array;

    /**
     * @param RevisionInterface $revision
     * @param array             $options
     *
     * @return ObjectAudit[]
     */
    public function findAllChangesAtRevision(RevisionInterface $revision, array $options = array()): array;

    /**
     * @param string            $className
     * @param RevisionInterface $revision
     * @param array             $options
     *
     * @throws ObjectNotAuditedException
     *
     * @return ObjectAudit[]
     */
    public function findChangesAtRevision(string $className, RevisionInterface $revision, array $options = array()): array;

    /**
     * Find all revisions that were made of object class with given id.
     *
     * @param string $className
     * @param mixed  $objectIds
     *
     * @throws ObjectNotAuditedException
     *
     * @return Collection|RevisionInterface[]
     */
    public function getRevisions(string $className, $objectIds): Collection;

    /**
     * @param string $className
     * @param mixed  $objectIds
     * @param array  $options
     *
     * @return ObjectAudit[]
     *
     * @throws ObjectAuditNotFoundException
     * @throws ObjectNotAuditedException
     */
    public function getHistory(string $className, $objectIds, array $options = array()): array;

    /**
     * Gets the initialize revision of the object with given ID.
     *
     * @param string $className
     * @param mixed  $objectIds
     *
     * @throws ObjectNotAuditedException
     *
     * @return RevisionInterface|null
     */
    public function getInitRevision(string $className, $objectIds);

    /**
     * Gets the current revision of the object with given ID.
     *
     * @param string $className
     * @param mixed  $objectIds
     *
     * @throws ObjectNotAuditedException
     * @throws ObjectAuditNotFoundException
     *
     * @return RevisionInterface|null
     */
    public function getRevision(string $className, $objectIds);

    /**
     * @param ObjectAudit $objectAudit
     *
     * @throws ObjectNotAuditedException
     *
     * @return $this
     */
    public function saveAudit(ObjectAudit $objectAudit);

    /**
     * Get an array with the differences of between two specific revisions of
     * an object with a given id.
     *
     * @param string            $className
     * @param mixed             $objectIds
     * @param RevisionInterface $oldRevision
     * @param RevisionInterface $newRevision
     *
     * @throws ObjectNotAuditedException
     *
     * @return array
     */
    public function diffRevisions(string $className, $objectIds, RevisionInterface $oldRevision, RevisionInterface $newRevision): array;

    /**
     * Get the values for a specific object as an associative array.
     *
     * @param object $object
     *
     * @return array
     */
    public function getValues($object): array;

    /**
     * @return BaseAuditConfiguration
     */
    public function getConfiguration(): BaseAuditConfiguration;

    /**
     * @return ObjectManager
     */
    public function getPersistManager(): ObjectManager;

    /**
     * @return ObjectManager
     */
    public function getAuditPersistManager(): ObjectManager;

    /**
     * @return RevisionManagerInterface
     */
    public function getRevisionManager(): RevisionManagerInterface;

    /**
     * @return ObjectAuditMetadataFactory
     */
    public function getMetadataFactory(): ObjectAuditMetadataFactory;
}
