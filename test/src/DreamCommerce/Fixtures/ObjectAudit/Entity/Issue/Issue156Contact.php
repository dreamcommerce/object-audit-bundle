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

/**
 * Created by PhpStorm.
 * User: david
 * Date: 23/02/2016
 * Time: 15:57
 */

namespace DreamCommerce\Fixtures\ObjectAudit\Entity\Issue;

use Doctrine\\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation\Auditable;

/**
 * @Auditable
 * @ORM\Entity()
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discriminator", type="string")
 */
class Issue156Contact
{
    /** @var int @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue(strategy="AUTO") */
    protected $id;

    /**
     * @var ArrayCollection|Issue156ContactTelephoneNumber[]
     * ORM\OneToMany(targetEntity="Issue156ContactTelephoneNumber", mappedBy="contact")
     */
    protected $telephoneNumbers;

    public function __construct()
    {
        $this->telephoneNumbers = new ArrayCollection();
    }

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Issue156ContactTelephoneNumber $telephoneNumber
     * @return $this
     */
    public function addTelephoneNumber(Issue156ContactTelephoneNumber $telephoneNumber)
    {
        if (!$this->telephoneNumbers->contains($telephoneNumber)) {
            $telephoneNumber->setContact($this);
            $this->telephoneNumbers[] = $telephoneNumber;
        }

        return $this;
    }

    /**
     * @param Issue156ContactTelephoneNumber $telephoneNumber
     * @return $this
     */
    public function removeTelephoneNumber(Issue156ContactTelephoneNumber $telephoneNumber)
    {
        $this->telephoneNumbers->removeElement($telephoneNumber);

        return $this;
    }

    /**
     * @return ArrayCollection|Issue156ContactTelephoneNumber[]
     */
    public function getTelephoneNumbers()
    {
        return $this->telephoneNumbers;
    }
}
