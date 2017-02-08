<?php

namespace DreamCommerce\Component\ObjectAudit\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

class ORMRevisionManager extends BaseRevisionManager
{
    /**
     * {@inheritdoc}
     */
    public function saveCurrentRevision()
    {
        if ($this->currentRevision !== null) {
            /** @var EntityManagerInterface $auditPersistManager */
            $auditPersistManager = $this->auditPersistManager;
            $uow = $auditPersistManager->getUnitOfWork();
            /** @var ClassMetadata $revisionMetadata */
            $revisionMetadata = $this->getRevisionMetadata();

            $uow->persist($this->currentRevision);
            $uow->computeChangeSet($revisionMetadata, $this->currentRevision);
            $auditPersistManager->flush();

            $this->currentRevision = null;
        }
    }
}
