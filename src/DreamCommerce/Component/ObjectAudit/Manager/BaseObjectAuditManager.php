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

use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Configuration\BaseAuditConfiguration;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Factory\ObjectAuditFactoryInterface;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadataFactory;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

abstract class BaseObjectAuditManager implements ObjectAuditManagerInterface
{
    /**
     * @var BaseAuditConfiguration
     */
    protected $configuration;

    /**
     * @var ObjectAuditFactoryInterface
     */
    protected $objectAuditFactory;

    /**
     * @var ObjectManager
     */
    protected $persistManager;

    /**
     * @var ObjectManager
     */
    protected $auditPersistManager;

    /**
     * @var ObjectAuditMetadataFactory
     */
    protected $objectAuditMetadataFactory;

    /**
     * @var RevisionManagerInterface
     */
    protected $revisionManager;

    /**
     * @param BaseAuditConfiguration      $configuration
     * @param ObjectManager               $persistManager
     * @param ObjectManager               $auditPersistManager
     * @param RevisionManagerInterface    $revisionManager
     * @param ObjectAuditFactoryInterface $objectAuditFactory
     * @param ObjectAuditMetadataFactory  $objectAuditMetadataFactory
     */
    public function __construct(BaseAuditConfiguration $configuration,
                                ObjectManager $persistManager,
                                ObjectManager $auditPersistManager,
                                RevisionManagerInterface $revisionManager,
                                ObjectAuditFactoryInterface $objectAuditFactory,
                                ObjectAuditMetadataFactory $objectAuditMetadataFactory
    ) {
        $this->configuration = $configuration;
        $this->persistManager = $persistManager;
        $this->auditPersistManager = $auditPersistManager;
        $this->revisionManager = $revisionManager;
        $this->objectAuditFactory = $objectAuditFactory;
        $this->objectAuditMetadataFactory = $objectAuditMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function diffObjectRevisions(string $className, $objectId, RevisionInterface $oldRevision, RevisionInterface $newRevision): array
    {
        if (!$this->objectAuditMetadataFactory->isClassAudited($className)) {
            throw ObjectNotAuditedException::forClass($className);
        }

        $oldObject = $this->findObjectByRevision($className, $objectId, $oldRevision);
        $newObject = $this->findObjectByRevision($className, $objectId, $newRevision);

        $oldData = $this->getObjectValues($oldObject);
        $newData = $this->getObjectValues($newObject);

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
    public function findAllObjectsChangedAtRevision(RevisionInterface $revision, array $options = array()): array
    {
        $result = array();
        foreach ($this->objectAuditMetadataFactory->getAllClassNames() as $auditClass) {
            $result = array_merge(
                $result,
                $this->findObjectsChangedAtRevision($auditClass, $revision, $options)
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): BaseAuditConfiguration
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getPersistManager(): ObjectManager
    {
        return $this->persistManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuditPersistManager(): ObjectManager
    {
        return $this->auditPersistManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getRevisionManager(): RevisionManagerInterface
    {
        return $this->revisionManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectAuditMetadataFactory(): ObjectAuditMetadataFactory
    {
        return $this->objectAuditMetadataFactory;
    }
}
