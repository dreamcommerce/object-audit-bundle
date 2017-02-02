<?php

namespace DreamCommerce\Component\ObjectAudit\Factory;

use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ObjectAuditManagerInterface;
use stdClass;
use Sylius\Component\Resource\Factory\FactoryInterface;

interface ObjectAuditFactoryInterface extends FactoryInterface
{
    /**
     * @param string                      $className
     * @param array                       $columnMap
     * @param array                       $data
     * @param RevisionInterface           $revision
     * @param ObjectAuditManagerInterface $objectAuditManager
     * @param ObjectManager               $objectManager
     *
     * @return stdClass
     */
    public function createNewAudit(string $className, array $columnMap, array $data, RevisionInterface $revision,
                                   ObjectAuditManagerInterface $objectAuditManager, ObjectManager $objectManager);

    /**
     * @return $this
     */
    public function clearAuditCache();
}
