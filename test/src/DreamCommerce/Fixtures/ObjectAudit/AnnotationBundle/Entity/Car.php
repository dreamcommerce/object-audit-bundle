<?php

namespace DreamCommerce\Fixtures\ObjectAudit\AnnotationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation as Audit;
use Sylius\Component\Resource\Model\ResourceInterface;

/**
 * @Audit\Auditable
 * @ORM\Entity
 */
class Car extends Vehicle implements ResourceInterface
{
    /**
     * @ORM\Column(type="string")
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