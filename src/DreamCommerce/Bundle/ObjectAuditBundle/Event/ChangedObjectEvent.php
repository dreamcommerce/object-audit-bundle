<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Event;

use DreamCommerce\Component\ObjectAudit\Model\ChangedObject;
use Symfony\Component\EventDispatcher\Event;

final class ChangedObjectEvent extends Event
{
    /**
     * @var ChangedObject
     */
    protected $changedObject;

    public function __construct(ChangedObject $changedObject)
    {
        $this->changedObject = $changedObject;
    }

    /**
     * @return ChangedObject
     */
    public function getChangedObject(): ChangedObject
    {
        return $this->changedObject;
    }
}