<?php
/*
 * (c) 2011 SimpleThings GmbH
 *
 * @package DreamCommerce\Component\ObjectAudit
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

namespace DreamCommerce\Component\ObjectAudit;

class ObjectAuditConfiguration
{
    /**
     * @var array
     */
    protected $auditedClasses = [];

    /**
     * @var array
     */
    protected $globalIgnoreProperties = [];

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
    protected $loadAuditedEntities = true;

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
    protected $loadNativeEntities = true;

    /**
     * @param string $className
     *
     * @return bool
     */
    public function isClassAudited(string $className): bool
    {
        return in_array($className, $this->auditedClasses);
    }

    /**
     * @return array
     */
    public function getAuditedClasses(): array
    {
        return $this->auditedClasses;
    }

    /**
     * @param array $classes
     *
     * @return $this
     */
    public function setAuditedClasses(array $classes)
    {
        $this->auditedClasses = $classes;

        return $this;
    }

    /**
     * @param string $auditedClass
     *
     * @return $this
     */
    public function addAuditedClass(string $auditedClass)
    {
        if (!$this->isClassAudited($auditedClass)) {
            $this->auditedClasses[] = $auditedClass;
        }

        return $this;
    }

    /**
     * @param string $auditedClass
     *
     * @return $this
     */
    public function removeAuditedClass(string $auditedClass)
    {
        if ($this->isClassAudited($auditedClass)) {
            $pos = array_search($auditedClass, $this->auditedClasses);
            if ($pos !== false) {
                unset($this->auditedClasses[$pos]);
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getGlobalIgnoreProperties(): array
    {
        return $this->globalIgnoreProperties;
    }

    /**
     * @param array $properties
     *
     * @return $this
     */
    public function setGlobalIgnoreProperties(array $properties)
    {
        $this->globalIgnoreProperties = $properties;

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
    public function isLoadAuditedEntities(): bool
    {
        return $this->loadAuditedEntities;
    }

    /**
     * @param bool $loadAuditedEntities
     *
     * @return $this
     */
    public function setLoadAuditedEntities(bool $loadAuditedEntities)
    {
        $this->loadAuditedEntities = $loadAuditedEntities;

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
    public function isLoadNativeEntities(): bool
    {
        return $this->loadNativeEntities;
    }

    /**
     * @param bool $loadNativeEntities
     *
     * @return $this
     */
    public function setLoadNativeEntities(bool $loadNativeEntities)
    {
        $this->loadNativeEntities = $loadNativeEntities;

        return $this;
    }
}
