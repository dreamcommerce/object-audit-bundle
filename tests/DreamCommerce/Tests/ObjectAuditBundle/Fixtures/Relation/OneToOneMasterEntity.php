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

namespace DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity */
class OneToOneMasterEntity
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue(strategy="AUTO") */
    protected $id;

    /** @ORM\Column(type="string") */
    protected $title;

    /** @ORM\OneToOne(targetEntity="OneToOneAuditedEntity") @ORM\JoinColumn(onDelete="SET NULL") */
    protected $audited;

    /** @ORM\OneToOne(targetEntity="OneToOneNotAuditedEntity") */
    protected $notAudited;

    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getAudited()
    {
        return $this->audited;
    }

    public function setAudited($audited)
    {
        $this->audited = $audited;
    }

    public function getNotAudited()
    {
        return $this->notAudited;
    }

    public function setNotAudited($notAudited)
    {
        $this->notAudited = $notAudited;
    }
}
