<?php

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
    public function getAuditPersistManager(): ObjectManager
    {
        return $this->auditPersistManager;
    }
}
