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

namespace DreamCommerce\Bundle\ObjectAuditBundle;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotFoundException;
use DreamCommerce\Component\ObjectAudit\Model\ChangedObject;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ObjectAuditConfiguration;
use DreamCommerce\Component\ObjectAudit\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Repository\RevisionRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

abstract class BaseObjectAuditManager implements ObjectAuditManagerInterface
{
    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    /**
     * @var ObjectAuditConfiguration
     */
    protected $configuration;

    /**
     * @var RevisionInterface|null
     */
    protected $currentRevision;

    /**
     * @var string|null
     */
    protected $revisionClass;

    /**
     * @var RevisionRepositoryInterface|null
     */
    protected $revisionRepository;

    /**
     * @var FactoryInterface
     */
    protected $revisionFactory;

    /**
     * @var ObjectManager
     */
    protected $auditObjectManager;

    /**
     * @var ObjectManager
     */
    protected $defaultObjectManager;

    /**
     * @param ObjectAuditConfiguration    $configuration
     * @param string                      $revisionClass
     * @param FactoryInterface            $revisionFactory
     * @param RevisionRepositoryInterface $revisionRepository
     * @param ObjectManager               $defaultObjectManager
     * @param ObjectManager|null          $auditObjectManager
     */
    public function __construct(ObjectAuditConfiguration $configuration,
                                string $revisionClass,
                                FactoryInterface $revisionFactory,
                                RevisionRepositoryInterface $revisionRepository,
                                ObjectManager $defaultObjectManager,
                                ObjectManager $auditObjectManager = null
    ) {
        $this->configuration = $configuration;
        $this->revisionClass = $revisionClass;
        $this->revisionFactory = $revisionFactory;
        $this->revisionRepository = $revisionRepository;
        $this->defaultObjectManager = $defaultObjectManager;
        $this->auditObjectManager = $auditObjectManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentRevision()
    {
        if ($this->currentRevision === null) {
            $this->currentRevision = $this->revisionFactory->createNew();
        }

        return $this->currentRevision;
    }

    /**
     * {@inheritdoc}
     */
    public function saveCurrentRevision()
    {
        if ($this->currentRevision !== null) {
            $this->auditObjectManager->persist($this->currentRevision);
            $this->currentRevision = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function diffObjectRevisions(string $className, $objectId, RevisionInterface $oldRevision, RevisionInterface $newRevision, ObjectManager $objectManager = null): array
    {
        if (!$this->getConfiguration()->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        $oldObject = $this->findObjectByRevision($className, $objectId, $oldRevision, $objectManager);
        $newObject = $this->findObjectByRevision($className, $objectId, $newRevision, $objectManager);

        $oldData = $this->getObjectValues($oldObject, $objectManager);
        $newData = $this->getObjectValues($newObject, $objectManager);

        $diff = array();
        $keys = array_keys($oldData) + array_keys($newData);
        foreach ($keys as $field) {
            $old = array_key_exists($field, $oldData) ? $oldData[$field] : null;
            $new = array_key_exists($field, $newData) ? $newData[$field] : null;

            if ($old == $new) {
                $row = array('old' => null, 'new' => null, 'same' => $old);
            } else {
                $row = array('old' => $old, 'new' => $new, 'same' => null);
            }

            $diff[$field] = $row;
        }

        return $diff;
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectValues($object, ObjectManager $objectManager = null): array
    {
        Assert::object($object);

        if ($objectManager === null) {
            $objectManager = $this->getDefaultObjectManager();
        }
        $objectClass = get_class($object);
        $metadata = $objectManager->getClassMetadata($objectClass);
        $fields = $metadata->getFieldNames();

        $return = array();
        foreach ($fields as $fieldName) {
            $return[$fieldName] = $metadata->getFieldValue($object, $fieldName);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function findAllObjectsChangedAtRevision(RevisionInterface $revision, ObjectManager $objectManager = null, array $options = array()): array
    {
        $result = array();
        foreach ($this->getConfiguration()->getAuditedClasses() as $auditedClass) {
            $result = array_merge(
                $result,
                $this->findObjectsChangedAtRevision($auditedClass, $revision, $objectManager, $options)
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): ObjectAuditConfiguration
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getRevisionRepository(): RevisionRepositoryInterface
    {
        return $this->revisionRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getRevisionClass(): string
    {
        return $this->revisionClass;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultObjectManager(): ObjectManager
    {
        return $this->defaultObjectManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuditObjectManager(): ObjectManager
    {
        if ($this->auditObjectManager === null) {
            $this->auditObjectManager = $this->getDefaultObjectManager();
        }

        return $this->auditObjectManager;
    }
}
