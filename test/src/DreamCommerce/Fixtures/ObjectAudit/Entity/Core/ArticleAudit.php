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

namespace DreamCommerce\Fixtures\ObjectAudit\Entity\Core;

use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation as Audit;

/**
 * @Audit\Auditable
 * @ORM\Entity
 */
class ArticleAudit
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    private $id;

    /** @ORM\Column(type="string", name="my_title_column") */
    private $title;

    /** @ORM\Column(type="text") */
    private $text;

    /** @ORM\Column(type="text") */
    private $globalIgnoreMe;

    /**
     * @Audit\Ignore
     * @ORM\Column(type="text")
     */
    private $localIgnoreMe;

    /** @ORM\ManyToOne(targetEntity="UserAudit") */
    private $author;

    public function __construct($title, $text, $author, $globalIgnoreMe, $localIgnoreMe)
    {
        $this->title    = $title;
        $this->text     = $text;
        $this->author   = $author;
        $this->globalIgnoreMe = $globalIgnoreMe;
        $this->localIgnoreMe = $localIgnoreMe;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return UserAudit
     */
    public function getAuthor()
    {
        return $this->author;
    }

    public function setText($text)
    {
        $this->text = $text;
    }

    public function setGlobalIgnoreMe($globalIgnoreMe)
    {
        $this->globalIgnoreMe = $globalIgnoreMe;
    }

    public function setLocalIgnoreMe($localIgnoreMe)
    {
        $this->localIgnoreMe = $localIgnoreMe;
    }
}
