<?php

namespace DreamCommerce\Component\ObjectAudit\Factory;

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
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
     *
     * @return stdClass
     */
    public function createNewAudit(string $className, array $columnMap, array $data, RevisionInterface $revision,
                                   ObjectAuditManagerInterface $objectAuditManager);

    /**
     * @return $this
     */
    public function clearAuditCache();
}
