<?php

namespace DreamCommerce\Component\ObjectAudit\Manager;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\Repository\RevisionRepositoryInterface;

interface RevisionManagerInterface
{
    /**
     * @return string
     */
    public function getRevisionClassName(): string;

    /**
     * @return RevisionRepositoryInterface
     */
    public function getRevisionRepository(): RevisionRepositoryInterface;

    /**
     * @return ClassMetadata
     */
    public function getRevisionMetadata(): ClassMetadata;

    /**
     * @return ObjectManager
     */
    public function getAuditPersistManager(): ObjectManager;

    /**
     * @return RevisionInterface|null
     */
    public function getCurrentRevision();

    /**
     * Save & clear old revision pointer.
     */
    public function saveCurrentRevision();
}