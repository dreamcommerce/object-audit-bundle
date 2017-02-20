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

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\Repository\RevisionRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

abstract class BaseRevisionManager implements RevisionManagerInterface
{
    /**
     * @var string
     */
    protected $revisionClassName;

    /**
     * @var RevisionInterface|null
     */
    protected $currentRevision;

    /**
     * @var ObjectManager
     */
    protected $auditPersistManager;

    /**
     * @var RevisionRepositoryInterface|null
     */
    protected $revisionRepository;

    /**
     * @var FactoryInterface
     */
    protected $revisionFactory;

    /**
     * @param string                      $revisionClassName
     * @param ObjectManager               $auditPersistManager
     * @param FactoryInterface            $revisionFactory
     * @param RevisionRepositoryInterface $revisionRepository
     */
    public function __construct(string $revisionClassName,
                                ObjectManager $auditPersistManager,
                                FactoryInterface $revisionFactory,
                                RevisionRepositoryInterface $revisionRepository)
    {
        $this->auditPersistManager = $auditPersistManager;
        $this->revisionClassName = $revisionClassName;
        $this->revisionRepository = $revisionRepository;
        $this->revisionFactory = $revisionFactory;
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
    public function getRevisionRepository(): RevisionRepositoryInterface
    {
        return $this->revisionRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getRevisionMetadata(): ClassMetadata
    {
        return $this->auditPersistManager->getClassMetadata($this->revisionClassName);
    }

    /**
     * {@inheritdoc}
     */
    public function getRevisionClassName(): string
    {
        return $this->revisionClassName;
    }

    /**
     * {@inheritdoc}
     */
    public function getRevisionPersistManager(): ObjectManager
    {
        return $this->auditPersistManager;
    }
}
