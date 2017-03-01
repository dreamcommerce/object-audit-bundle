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
    protected $className;

    /**
     * @var RevisionInterface|null
     */
    protected $revision;

    /**
     * @var ObjectManager
     */
    protected $persistManager;

    /**
     * @var RevisionRepositoryInterface|null
     */
    protected $repository;

    /**
     * @var FactoryInterface
     */
    protected $factory;

    /**
     * @param string                      $className
     * @param ObjectManager               $persistManager
     * @param FactoryInterface            $factory
     * @param RevisionRepositoryInterface $repository
     */
    public function __construct(string $className,
                                ObjectManager $persistManager,
                                FactoryInterface $factory,
                                RevisionRepositoryInterface $repository)
    {
        $this->persistManager = $persistManager;
        $this->className = $className;
        $this->repository = $repository;
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function getRevision(): RevisionInterface
    {
        if ($this->revision === null) {
            $this->revision = $this->factory->createNew();
        }

        return $this->revision;
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository(): RevisionRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): ClassMetadata
    {
        return $this->persistManager->getClassMetadata($this->className);
    }

    /**
     * {@inheritdoc}
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * {@inheritdoc}
     */
    public function getPersistManager(): ObjectManager
    {
        return $this->persistManager;
    }
}
