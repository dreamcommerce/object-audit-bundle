<?php

namespace DreamCommerce\Component\ObjectAudit;

use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use SplObjectStorage;

final class ObjectAuditRegistry
{
    /**
     * @var array
     */
    private $objectAuditManagers = array();

    /**
     * @var SplObjectStorage
     */
    private $persistManagers;

    /**
     * @param string                      $name
     * @param ObjectManager               $persistManager
     * @param ObjectAuditManagerInterface $objectAuditManager
     */
    public function registerObjectAuditManager(string $name, ObjectManager $persistManager, ObjectAuditManagerInterface $objectAuditManager)
    {
        if ($this->persistManagers === null) {
            $this->persistManagers = new SplObjectStorage();
        }

        $this->objectAuditManagers[$name] = $objectAuditManager;
        $this->persistManagers[$persistManager] = $objectAuditManager;
    }

    /**
     * @param string $name
     *
     * @return ObjectAuditManagerInterface|null
     */
    public function getByName(string $name)
    {
        if (!isset($this->objectAuditManagers[$name])) {
            return null;
        }

        return $this->objectAuditManagers[$name];
    }

    /**
     * @param ObjectManager $persistManager
     *
     * @return null|ObjectAuditManagerInterface
     */
    public function getByPersistManager(ObjectManager $persistManager)
    {
        if (!isset($this->persistManagers[$persistManager])) {
            return null;
        }

        return $this->persistManagers[$persistManager];
    }
}
