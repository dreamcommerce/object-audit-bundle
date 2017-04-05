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

use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadata;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

class YamlDriver extends FileDriver
{
    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass(string $className, ObjectAuditMetadata $objectAuditMetadata)
    {
        $reflection = new ReflectionClass($className);
        $parentClassName = $reflection->getParentClass();
        if ($parentClassName) {
            $this->loadMetadataForClass($parentClassName->name, $objectAuditMetadata);
        }

        $mapping = $this->_getMapping($className);
        if ($mapping === null) {
            return;
        }

        if (isset($mapping['fields'])) {
            foreach ($mapping['fields'] as $field => $fieldMapping) {
                if (isset($fieldMapping['dreamcommerce']['ignore']) && $fieldMapping['dreamcommerce']['ignore']) {
                    $objectAuditMetadata->ignoredProperties[] = $field;
                }
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
            if ($parentDriver === null) {
                $parentDriver = $this;
            }
            if ($parentDriver->isTransient($parentClassName->name)) {
                return true;
            }
        }

        $mapping = $this->_getMapping($className);
        if ($mapping === null) {
            return false;
        }

        return isset($mapping['dreamcommerce']['auditable']) && $mapping['dreamcommerce']['auditable'];
    }

    /**
     * {@inheritDoc}
     */
    protected function loadMappingFile(string $file): array
    {
        return Yaml::parse(file_get_contents($file));
    }
}
