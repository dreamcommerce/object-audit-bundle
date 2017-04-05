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
use SimpleXMLElement;

class XmlDriver extends FileDriver
{
    const DREAM_COMMERCE_NAMESPACE_URI = 'https://dreamcommerce.com/schemas/orm/doctrine-object-audit-mapping';
    const DOCTRINE_NAMESPACE_URI = 'http://doctrine-project.org/schemas/orm/doctrine-mapping';

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

        $xml = $this->_getMapping($className);
        if ($xml === null) {
            return;
        }

        if (isset($xml->field)) {
            /** @var SimpleXMLElement $mapping */
            foreach ($xml->field as $mapping) {
                $mappingDoctrine = $mapping;
                $mapping = $mapping->children(self::DREAM_COMMERCE_NAMESPACE_URI);

                if (isset($mapping->ignore)) {
                    $fieldName = (string)$mappingDoctrine->attributes()->name;
                    if (!in_array($fieldName, $objectAuditMetadata->ignoredProperties)) {
                        $objectAuditMetadata->ignoredProperties[] = $fieldName;
                    }
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
            if($parentDriver === null) {
                $parentDriver = $this;
            }
            if($parentDriver->isTransient($parentClassName->name)) {
                return true;
            }
        }

        $xml = $this->_getMapping($className);
        if ($xml === null) {
            return false;
        }

        $xmlDoctrine = $xml;
        $xml = $xml->children(self::DREAM_COMMERCE_NAMESPACE_URI);

        if ($xmlDoctrine->getName() == 'entity' || $xmlDoctrine->getName() == 'document' || $xmlDoctrine->getName() == 'mapped-superclass') {
            return isset($xml->auditable);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function loadMappingFile(string $fileName): array
    {
        $result = array();
        $xmlElement = simplexml_load_file($fileName);
        $xmlElement = $xmlElement->children(self::DOCTRINE_NAMESPACE_URI);
        if (isset($xmlElement->entity)) {
            foreach ($xmlElement->entity as $entityElement) {
                $entityName = $this->getAttribute($entityElement, 'name');
                $result[$entityName] = $entityElement;
            }
        } elseif (isset($xmlElement->{'mapped-superclass'})) {
            foreach ($xmlElement->{'mapped-superclass'} as $mappedSuperClass) {
                $className = $this->getAttribute($mappedSuperClass, 'name');
                $result[$className] = $mappedSuperClass;
            }
        }
        return $result;
    }

    /**
     * Get attribute value.
     * As we are supporting namespaces the only way to get to the attributes under a node is to use attributes function on it
     *
     * @param SimpleXMLElement $node
     * @param string           $attributeName
     *
     * @return string
     */
    protected function getAttribute(SimpleXMLElement $node, string $attributeName): string
    {
        $attributes = $node->attributes();
        return (string) $attributes[$attributeName];
    }
}
