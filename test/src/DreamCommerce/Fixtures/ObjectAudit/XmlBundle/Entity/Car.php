<?php

namespace DreamCommerce\Fixtures\ObjectAudit\XmlBundle\Entity;

use Sylius\Component\Resource\Model\ResourceInterface;

class Car extends Vehicle implements ResourceInterface
{
    /**
     * @var string
     */
    private $ignoredField;

    /**
     * @param string $name
     * @param string $ignoredField
     */
    public function __construct(string $name, string $ignoredField)
    {
        parent::__construct($name);
        $this->ignoredField = $ignoredField;
    }

    /**
     * @return string
     */
    public function getIgnoredField(): string
    {
        return $this->ignoredField;
    }

    /**
     * @param string $ignoredField
     */
    public function setIgnoredField(string $ignoredField)
    {
        $this->ignoredField = $ignoredField;
    }
}