<?php

namespace DreamCommerce\Fixtures\ObjectAudit\AnnotationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation as Audit;

/**
 * @Audit\Auditable
 * @ORM\Entity
 */
class Ship extends Vehicle
{

}