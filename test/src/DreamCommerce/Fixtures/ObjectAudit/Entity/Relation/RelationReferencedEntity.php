<?php

/*
 * (c) 2017 DreamCommerce
 *
 * @package DreamCommerce\Component\ObjectAudit
 * @author Michał Korus <michal.korus@dreamcommerce.com>
 * @link https://www.dreamcommerce.com
 *
 * (c) 2011 SimpleThings GmbH
 *
 * @package SimpleThings\EntityAudit
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 * @link http://www.simplethings.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

namespace DreamCommerce\Fixtures\ObjectAudit\Entity\Relation;

use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation\Auditable;

/**
 * @Auditable
 * @ORM\Entity
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({ "foobar" = "RelationFoobarEntity" })
 */
abstract class RelationReferencedEntity extends RelationAbstractEntityBase
{
    /** @ORM\Column(type="string") */
    protected $referencedField;

    /** @ORM\OneToOne(targetEntity="RelationOneToOneEntity", mappedBy="referencedEntity") */
    protected $oneToOne;

    public function getOneToOne()
    {
        return $this->oneToOne;
    }

    public function setOneToOne($oneToOne)
    {
        $this->oneToOne = $oneToOne;
    }

    public function getReferencedField()
    {
        return $this->referencedField;
    }

    public function setReferencedField($referencedField)
    {
        $this->referencedField = $referencedField;
    }
}
