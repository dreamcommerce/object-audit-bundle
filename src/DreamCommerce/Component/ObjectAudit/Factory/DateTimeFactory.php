<?php

namespace DreamCommerce\Component\ObjectAudit\Factory;

use DateTime;
use Sylius\Component\Resource\Factory\FactoryInterface;

class DateTimeFactory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createNew()
    {
        return new DateTime();
    }
}
