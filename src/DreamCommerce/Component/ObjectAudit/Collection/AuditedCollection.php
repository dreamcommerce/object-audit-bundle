<?php
/*
 * (c) 2011 SimpleThings GmbH
 *
 * @package DreamCommerce\Component\ObjectAudit
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 * @author Andrew Tch <andrew.tchircoff@gmail.com>
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

namespace DreamCommerce\Component\ObjectAudit\Collection;

use ArrayIterator;
use Closure;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use DreamCommerce\Component\ObjectAudit\Exception\AuditedCollectionException;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ObjectAuditManagerInterface;

class AuditedCollection implements Collection
{
    /**
     * Related audit manager instance.
     *
     * @var ObjectAuditManagerInterface
     */
    protected $auditManager;

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
     * @var RevisionInterface
     */
    protected $revision;

    /**
     * @var ClassMetadataInfo
     */
    protected $metadata;

    /**
     * Object array. If can be:
     * - empty, if the collection has not been initialized yet
     * - store object
     * - contain audited object.
     *
     * @var array
     */
    protected $objects = [];

    /**
     * Definition of current association.
     *
     * @var array
     */
    protected $associationDefinition = [];

    /**
     * @var bool
     */
    protected $initialized = false;

    /**
     * @param ObjectAuditManagerInterface $auditManager
     * @param string                      $className
     * @param ClassMetadataInfo           $classMeta
     * @param RevisionInterface           $revision
     * @param array                       $associationDefinition
     * @param array                       $foreignKeys
     */
    public function __construct(ObjectAuditManagerInterface $auditManager, string $className, ClassMetadataInfo $classMeta,
                                RevisionInterface $revision, array $associationDefinition, array $foreignKeys)
    {
        $this->auditManager = $auditManager;
        $this->className = $className;
        $this->foreignKeys = $foreignKeys;
        $this->revision = $revision;
        $this->metadata = $classMeta;
        $this->associationDefinition = $associationDefinition;
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
        $this->forceLoad();

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
        $this->forceLoad();

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
        $this->forceLoad();

        return $this->objects;
    }

    /**
     * {@inheritdoc}
     */
    public function first()
    {
        $this->forceLoad();

        return reset($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function last()
    {
        $this->forceLoad();

        return end($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        $this->forceLoad();

        return key($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        $this->forceLoad();

        return current($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->forceLoad();

        return next($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Closure $p)
    {
        $this->forceLoad();

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
        $this->forceLoad();

        return array_filter($this->objects, $p);
    }

    /**
     * {@inheritdoc}
     */
    public function forAll(Closure $p)
    {
        $this->forceLoad();

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
        $this->forceLoad();

        return array_map($func, $this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function partition(Closure $p)
    {
        $this->forceLoad();

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
        $this->forceLoad();

        return array_search($element, $this->objects, true);
    }

    /**
     * {@inheritdoc}
     */
    public function slice($offset, $length = null)
    {
        $this->forceLoad();

        return array_slice($this->objects, $offset, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        $this->forceLoad();

        return new ArrayIterator($this->objects);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        $this->forceLoad();

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

        $entity = $this->objects[$offset];

        if (is_object($entity)) {
            return $entity;
        } else {
            return $this->objects[$offset] = $this->resolve($entity);
        }
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

    protected function resolve(array $entity)
    {
        return $this->auditManager
            ->findObjectByRevision(
                $this->className,
                $entity['keys'],
                $this->revision
            );
    }

    protected function forceLoad()
    {
        $this->initialize();

        foreach ($this->objects as $key => $entity) {
            if (is_array($entity)) {
                $this->objects[$key] = $this->resolve($entity);
            }
        }
    }

    protected function initialize()
    {
        if (!$this->initialized) {
            // TODO

            $this->initialized = true;
        }
    }
}
