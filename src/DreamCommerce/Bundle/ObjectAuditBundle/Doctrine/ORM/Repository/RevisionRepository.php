<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORM\Repository;

use DreamCommerce\Component\ObjectAudit\Repository\RevisionRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class RevisionRepository extends EntityRepository implements RevisionRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function findCurrentRevision()
    {
        $classMetadata = $this->getClassMetadata();
        $qb = $this->createQueryBuilder('r');
        foreach ($classMetadata->identifier as $identifier) {
            $qb->orderBy('r.'.$identifier, 'DESC');
        }
        $qb->setMaxResults(1);
        $query = $qb->getQuery();

        return $query->getOneOrNullResult();
    }
}
