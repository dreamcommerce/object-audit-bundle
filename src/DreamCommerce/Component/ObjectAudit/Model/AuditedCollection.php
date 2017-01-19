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

namespace DreamCommerce\Component\ObjectAudit\Model;

use ArrayIterator;
use Closure;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Exception\AuditedCollectionException;
use DreamCommerce\Component\ObjectAudit\ObjectAuditManagerInterface;

class AuditedCollection implements Collection
{
    /**
     * Related audit manager instance.
     *
     * @var ObjectAuditManagerInterface
     */
    protected $objectAuditManager;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * Class to fetch.
     *
     * @var string
     */
    protected $className;

    /**
     * Foreign keys for target object.
     *
     * @var array
     */
    protected $foreignKeys;

    /**
     * @var string|null
     */
    protected $indexBy;

    /**
     * @var RevisionInterface
     */
    protected $revision;

    /**
     * Object array. If can be:
     * - empty, if the collection has not been initialized yet
     * - store object
     * - contain audited object.
     *
     * @var array
     */
    protected $objects = array();

    /**
     * @var bool
     */
    protected $initialized = false;

    /**
     * @param string $className
     * @param array $foreignKeys
     * @param string|null $indexBy
     * @param RevisionInterface $revision
     * @param ObjectManager $objectManager
     * @param ObjectAuditManagerInterface $objectAuditManager
     */
    public function __construct(string $className, array $foreignKeys, string $indexBy = null, RevisionInterface $revision,
                                ObjectManager $objectManager, ObjectAuditManagerInterface $objectAuditManager)
    {
        $this->className = $className;
        $this->foreignKeys = $foreignKeys;
        $this->indexBy = $indexBy;
        $this->revision = $revision;
        $this->objectAuditManager = $objectAuditManager;
    }

    /**
     * {@inheritdoc}
     */
    public function add($element)
    {
        throw AuditedCollectionException::readOnly(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->objects = array();
        $this->initialized = false;
    }

    /**
     * {@inheritdoc}
     */
    public function contains($element)
    {
        $this->initialize();

        return (bool) array_search($element, $this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $this->initialize();

        return count($this->objects) == 0;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        throw AuditedCollectionException::readOnly(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function removeElement($element)
    {
        throw AuditedCollectionException::readOnly(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function containsKey($key)
    {
        $this->initialize();

        return array_key_exists($key, $this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        return $this->offsetGet($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getKeys()
    {
        $this->initialize();

        return array_keys($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        $this->initialize();

        return array_values($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        throw AuditedCollectionException::readOnly(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        $this->initialize();

        return $this->objects;
    }

    /**
     * {@inheritdoc}
     */
    public function first()
    {
        $this->initialize();

        return reset($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function last()
    {
        $this->initialize();

        return end($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        $this->initialize();

        return key($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        $this->initialize();

        return current($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->initialize();

        return next($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Closure $p)
    {
        $this->initialize();

        foreach ($this->objects as $entity) {
            if ($p($entity)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Closure $p)
    {
        $this->initialize();

        return array_filter($this->objects, $p);
    }

    /**
     * {@inheritdoc}
     */
    public function forAll(Closure $p)
    {
        $this->initialize();

        foreach ($this->objects as $entity) {
            if (!$p($entity)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function map(Closure $func)
    {
        $this->initialize();

        return array_map($func, $this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function partition(Closure $p)
    {
        $this->initialize();

        $true = $false = array();

        foreach ($this->objects as $entity) {
            if ($p($entity)) {
                $true[] = $entity;
            } else {
                $false[] = $entity;
            }
        }

        return array($true, $false);
    }

    /**
     * {@inheritdoc}
     */
    public function indexOf($element)
    {
        $this->initialize();

        return array_search($element, $this->objects, true);
    }

    /**
     * {@inheritdoc}
     */
    public function slice($offset, $length = null)
    {
        $this->initialize();

        return array_slice($this->objects, $offset, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $this->initialize();

        return new ArrayIterator($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        $this->initialize();

        return array_key_exists($offset, $this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        $this->initialize();

        if (!isset($this->objects[$offset])) {
            throw AuditedCollectionException::forNotDefinedOffset(__CLASS__, $offset);
        }

        return $this->objects[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        throw AuditedCollectionException::readOnly(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        throw AuditedCollectionException::readOnly(__CLASS__);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        $this->initialize();

        return count($this->objects);
    }

    protected function initialize()
    {
        if ($this->initialized) {
            return;
        }

        $this->objects = $this->objectAuditManager->findObjectsByFieldsAndRevision(
            $this->className,
            $this->foreignKeys,
            $this->indexBy,
            $this->revision,
            $this->objectManager
        );
        $this->initialized = true;
    }
}
