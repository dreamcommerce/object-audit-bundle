<?php

namespace DreamCommerce\Component\ObjectAudit\Repository;

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface RevisionRepositoryInterface extends RepositoryInterface
{
    /**
     * @return RevisionInterface|null
     */
    public function findCurrentRevision();
}
