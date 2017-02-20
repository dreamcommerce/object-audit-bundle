<?php

namespace DreamCommerce\Component\ObjectAudit\Metadata\Driver;

use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadata;
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
        $xml = $this->_getMapping($className);
        if($xml === null) {
            return;
        }

        if (isset($xml->field)) {
            /** @var SimpleXMLElement $mapping */
            foreach ($xml->field as $mapping) {
                $mappingDoctrine = $mapping;
                $mapping = $mapping->children(self::DREAM_COMMERCE_NAMESPACE_URI);

                if(isset($mapping->ignore)) {
                    $objectAuditMetadata->ignoredProperties[] = $mappingDoctrine->attributes()->name;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isTransient(string $className): bool
    {
        $xml = $this->_getMapping($className);
        if($xml === null) {
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