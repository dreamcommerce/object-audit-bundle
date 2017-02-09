<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation as Audit;
use Sylius\Component\Resource\Model\ResourceInterface;

/**
 * @Audit\Auditable
 * @ORM\Entity
 */
class Car implements ResourceInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $color;

    public function __construct(string $color)
    {
        $this->color = $color;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getColor()
    {
        return $this->color;
    }

    public function setColor($color)
    {
        $this->color = $color;
    }
}
