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

namespace DreamCommerce\Component\ObjectAudit\Metadata\Driver;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation\Auditable;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation\Ignore;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadata;
use ReflectionClass;

/**
 * @author David Badura <d.a.badura@gmail.com>
 */
class AnnotationDriver implements DriverInterface
{
    /**
     * @var AnnotationReader
     */
    private $reader;

    /**
     * @param AnnotationReader $reader
     */
    public function __construct(AnnotationReader $reader)
    {
        // use composer autoloader
        AnnotationRegistry::registerLoader('class_exists');
        $this->reader = $reader;
    }

    /**
     * @param string              $className
     * @param ObjectAuditMetadata $objectAuditMetadata
     */
    public function loadMetadataForClass(string $className, ObjectAuditMetadata $objectAuditMetadata)
    {
        $reflection = new ReflectionClass($className);
        $parentClassName = $reflection->getParentClass();
        if ($parentClassName) {
            $this->loadMetadataForClass($parentClassName->name, $objectAuditMetadata);
        }

        foreach ($reflection->getProperties() as $property) {
            if ($this->reader->getPropertyAnnotation($property, Ignore::class)) {
                $objectAuditMetadata->ignoredProperties[] = $property->name;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isTransient(string $className, DriverInterface $parentDriver = null): bool
    {
        $reflection = new ReflectionClass($className);
        $parentClassName = $reflection->getParentClass();
        if ($parentClassName) {
            if($parentDriver === null) {
                $parentDriver = $this;
            }
            if($parentDriver->isTransient($parentClassName->name)) {
                return true;
            }
        }

        return (bool) $this->reader->getClassAnnotation($reflection, Auditable::class);
    }
}
