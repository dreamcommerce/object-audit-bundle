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

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotFoundException;
use DreamCommerce\Component\ObjectAudit\Model\ChangedObject;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\Repository\RevisionRepositoryInterface;

interface ObjectAuditManagerInterface
{
    /**
     * Find an object at the specific revision.
     *
     * @param string             $className
     * @param mixed              $objectIds
     * @param RevisionInterface  $revision
     * @param ObjectManager|null $objectManager
     * @param array              $options
     *
     * @throws ObjectDeletedException
     * @throws ObjectNotFoundException
     * @throws ObjectNotAuditedException
     *
     * @return object
     */
    public function findObjectByRevision(string $className, $objectIds, RevisionInterface $revision, ObjectManager $objectManager = null, array $options = array());

    /**
     * @param string $className
     * @param array $fields
     * @param string|null $indexBy
     * @param RevisionInterface $revision
     * @param ObjectManager|null $objectManager
     * @param array $options
     *
     * @throws ObjectNotAuditedException
     *
     * @return array
     */
    public function findObjectsByFieldsAndRevision(string $className, array $fields, string $indexBy = null, RevisionInterface $revision, ObjectManager $objectManager = null, array $options = array()): array;

    /**
     * @param RevisionInterface  $revision
     * @param ObjectManager|null $objectManager
     * @param array              $options
     *
     * @return ChangedObject[]
     */
    public function findAllObjectsChangedAtRevision(RevisionInterface $revision, ObjectManager $objectManager = null, array $options = array()): array;

    /**
     * @param string             $className
     * @param RevisionInterface  $revision
     * @param ObjectManager|null $objectManager
     * @param array              $options
     *
     * @throws ObjectNotAuditedException
     *
     * @return ChangedObject[]
     */
    public function findObjectsChangedAtRevision(string $className, RevisionInterface $revision, ObjectManager $objectManager = null, array $options = array()): array;

    /**
     * Find all revisions that were made of object class with given id.
     *
     * @param string             $className
     * @param mixed              $objectId
     * @param ObjectManager|null $objectManager
     *
     * @throws ObjectNotAuditedException
     *
     * @return Collection|RevisionInterface[]
     */
    public function findObjectRevisions(string $className, $objectId, ObjectManager $objectManager = null): Collection;

    /**
     * @param string $className
     * @param mixed  $objectId
     * @param ObjectManager|null $objectManager
     * @param array              $options
     *
     * @return ChangedObject[]
     *
     * @throws ObjectNotFoundException
     * @throws ObjectNotAuditedException
     */
    public function getObjectHistory(string $className, $objectId, ObjectManager $objectManager = null, array $options = array()): array;

    /**
     * Gets the initialize revision of the object with given ID.
     *
     * @param string             $className
     * @param mixed              $objectId
     * @param ObjectManager|null $objectManager
     *
     * @throws ObjectNotAuditedException
     *
     * @return RevisionInterface|null
     */
    public function getInitializeObjectRevision(string $className, $objectId, ObjectManager $objectManager = null);

    /**
     * Gets the current revision of the object with given ID.
     *
     * @param string             $className
     * @param mixed              $objectId
     * @param ObjectManager|null $objectManager
     *
     * @throws ObjectNotAuditedException
     * @throws ObjectNotFoundException
     *
     * @return RevisionInterface|null
     */
    public function getCurrentObjectRevision(string $className, $objectId, ObjectManager $objectManager = null);

    /**
     * @param ChangedObject $changedObject
     *
     * @throws ObjectNotAuditedException
     *
     * @return $this
     */
    public function saveObjectRevisionData(ChangedObject $changedObject);

    /**
     * Get an array with the differences of between two specific revisions of
     * an object with a given id.
     *
     * @param string             $className
     * @param mixed              $objectId
     * @param RevisionInterface  $oldRevision
     * @param RevisionInterface  $newRevision
     * @param ObjectManager|null $objectManager
     *
     * @throws ObjectNotAuditedException
     *
     * @return array
     */
    public function diffObjectRevisions(string $className, $objectId, RevisionInterface $oldRevision, RevisionInterface $newRevision, ObjectManager $objectManager = null): array;

    /**
     * Get the values for a specific object as an associative array.
     *
     * @param object             $object
     * @param ObjectManager|null $objectManager
     *
     * @return array
     */
    public function getObjectValues($object, ObjectManager $objectManager = null): array;

    /**
     * @return ObjectAuditConfiguration
     */
    public function getConfiguration(): ObjectAuditConfiguration;

    /**
     * @return ObjectManager
     */
    public function getDefaultObjectManager(): ObjectManager;

    /**
     * @return ObjectManager
     */
    public function getAuditObjectManager(): ObjectManager;

    /**
     * @return string
     */
    public function getRevisionClass(): string;

    /**
     * @return RevisionRepositoryInterface
     */
    public function getRevisionRepository(): RevisionRepositoryInterface;

    /**
     * @return RevisionInterface|null
     */
    public function getCurrentRevision();

    /**
     * Save & clear old revision pointer.
     *
     * @return mixed
     */
    public function saveCurrentRevision();

    /**
     * @return $this
     */
    public function clearObjectCache();
}
