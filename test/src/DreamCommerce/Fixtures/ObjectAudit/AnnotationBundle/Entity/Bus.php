<?php

namespace DreamCommerce\Fixtures\ObjectAudit\AnnotationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation as Audit;
use Sylius\Component\Resource\Model\ResourceInterface;

/**
 * @Audit\Auditable
 * @ORM\Entity
 */
class Bus extends Vehicle implements ResourceInterface
{

}