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

declare(strict_types=1);

namespace DreamCommerce\Component\ObjectAudit\Model;

use Doctrine\Persistence\ObjectManager;
use Webmozart\Assert\Assert;

class ObjectAudit
{
    /**
     * @var string
     */
    private $className;

    /**
     * @var object
     */
    private $object;

    /**
     * @var array
     */
    private $identifiers;

    /**
     * @var RevisionInterface
     */
    private $revision;

    /**
     * @var ObjectManager
     */
    private $persistManager;

    /**
     * @var array
     */
    private $data = array();

    /**
     * @var string
     */
    private $type;

    /**
     * @param object            $object
     * @param string            $className
     * @param array             $identifiers
     * @param RevisionInterface $revision
     * @param ObjectManager     $persistManager
     * @param array             $data
     * @param string            $type
     */
    public function __construct($object, string $className = null, array $identifiers, RevisionInterface $revision,
                                ObjectManager $persistManager, array $data, string $type)
    {
        Assert::object($object);
        Assert::oneOf($type, array(RevisionInterface::ACTION_INSERT, RevisionInterface::ACTION_UPDATE, RevisionInterface::ACTION_DELETE));

        $this->data = $data;
        $this->type = $type;

        if ($className === null) {
            $className = get_class($object);
        }

        Assert::isInstanceOf($object, $className);

        $this->identifiers = $identifiers;
        $this->object = $object;
        $this->className = $className;
        $this->revision = $revision;
        $this->persistManager = $persistManager;
    }

    /**
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return array
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    /**
     * @return RevisionInterface
     */
    public function getRevision(): RevisionInterface
    {
        return $this->revision;
    }

    /**
     * @return ObjectManager
     */
    public function getPersistManager(): ObjectManager
    {
        return $this->persistManager;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     *
     * @return self
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
}
