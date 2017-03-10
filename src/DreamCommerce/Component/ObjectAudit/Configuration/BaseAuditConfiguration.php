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

namespace DreamCommerce\Component\ObjectAudit\Configuration;

use DreamCommerce\Component\Common\Model\ArrayableInterface;
use DreamCommerce\Component\Common\Model\ArrayableTrait;

class BaseAuditConfiguration implements ArrayableInterface
{
    use ArrayableTrait;

    /**
     * @var array
     */
    protected $ignoreProperties = array();

    /**
     * Decides if audited ToMany collections are loaded.
     *
     * @var bool
     */
    protected $loadAuditedCollections = true;

    /**
     * Decides if audited ToOne collections are loaded.
     *
     * @var bool
     */
    protected $loadAuditedObjects = true;

    /**
     * Decides if native (not audited) ToMany collections are loaded.
     *
     * @var bool
     */
    protected $loadNativeCollections = true;

    /**
     * Decides if native (not audited) ToOne collections are loaded.
     *
     * @var bool
     */
    protected $loadNativeObjects = true;

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->fromArray($options);
    }

    /**
     * @return array
     */
    public function getIgnoreProperties(): array
    {
        return $this->ignoreProperties;
    }

    /**
     * @param array $properties
     *
     * @return $this
     */
    public function setIgnoreProperties(array $properties)
    {
        $this->ignoreProperties = $properties;

        return $this;
    }

    /**
     * @return bool
     */
    public function isLoadAuditedCollections(): bool
    {
        return $this->loadAuditedCollections;
    }

    /**
     * @param bool $loadAuditedCollections
     *
     * @return $this
     */
    public function setLoadAuditedCollections(bool $loadAuditedCollections)
    {
        $this->loadAuditedCollections = $loadAuditedCollections;

        return $this;
    }

    /**
     * @return bool
     */
    public function isLoadAuditedObjects(): bool
    {
        return $this->loadAuditedObjects;
    }

    /**
     * @param bool $loadAuditedObjects
     *
     * @return $this
     */
    public function setLoadAuditedObjects(bool $loadAuditedObjects)
    {
        $this->loadAuditedObjects = $loadAuditedObjects;

        return $this;
    }

    /**
     * @return bool
     */
    public function isLoadNativeCollections(): bool
    {
        return $this->loadNativeCollections;
    }

    /**
     * @param bool $loadNativeCollections
     *
     * @return $this
     */
    public function setLoadNativeCollections(bool $loadNativeCollections)
    {
        $this->loadNativeCollections = $loadNativeCollections;

        return $this;
    }

    /**
     * @return bool
     */
    public function isLoadNativeObjects(): bool
    {
        return $this->loadNativeObjects;
    }

    /**
     * @param bool $loadNativeObjects
     *
     * @return $this
     */
    public function setLoadNativeObjects(bool $loadNativeObjects)
    {
        $this->loadNativeObjects = $loadNativeObjects;

        return $this;
    }
}
