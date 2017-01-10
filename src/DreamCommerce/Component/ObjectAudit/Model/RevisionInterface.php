<?php

namespace DreamCommerce\Component\ObjectAudit\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface RevisionInterface extends ResourceInterface
{
    const ACTION_INSERT = 'INS';
    const ACTION_UPDATE = 'UPD';
    const ACTION_DELETE = 'DEL';

    /**
     * @return int
     */
    public function getId();

    /**
     * @return \DateTime
     */
    public function getCreatedAt();
}
