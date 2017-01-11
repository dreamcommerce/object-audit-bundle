<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Event;

use DreamCommerce\Component\ObjectAudit\Model\ChangedResource;
use Symfony\Component\EventDispatcher\Event;

final class ChangedResourceEvent extends Event
{
    /**
     * @var ChangedResource
     */
    protected $changedResource;

    public function __construct(ChangedResource $changedResource)
    {
        $this->changedResource = $changedResource;
    }

    /**
     * @return ChangedResource
     */
    public function getChangedResource(): ChangedResource
    {
        return $this->changedResource;
    }
}