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

namespace DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Compiler;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Persistence\Mapping\Driver\AnnotationDriver;
use Doctrine\Common\Persistence\Mapping\Driver\DefaultFileLocator;
use Doctrine\Common\Persistence\Mapping\Driver\FileDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Persistence\Mapping\Driver\SymfonyFileLocator;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\DreamCommerceObjectAuditExtension;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\AnnotationDriver as AuditAnnotationDriver;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\MappingDriverChain as AuditMappingDriverChain;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\XmlDriver as AuditXmlDriver;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\YamlDriver as AuditYamlDriver;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ManagerCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->findDefinition(
            DreamCommerceObjectAuditExtension::ALIAS.'.registry'
        );

        foreach ($container->getParameter(DreamCommerceObjectAuditExtension::ALIAS.'.managers') as $name => $managerConfig) {
            $managerId = DreamCommerceObjectAuditExtension::ALIAS.'.'. $name .'_manager';
            $configurationId = DreamCommerceObjectAuditExtension::ALIAS.'.' . $name . '_configuration';
            $metadataFactoryId = DreamCommerceObjectAuditExtension::ALIAS.'.' . $name . '_metadata_factory';

            switch ($managerConfig['driver']) {
                case ObjectAuditManagerInterface::DRIVER_ORM:
                    $configurationClass = $container->getParameter(DreamCommerceObjectAuditExtension::ALIAS . '.orm.configuration.class');
                    $managerClass = $container->getParameter(DreamCommerceObjectAuditExtension::ALIAS . '.orm.manager.class');
                    $auditFactory = new Reference(DreamCommerceObjectAuditExtension::ALIAS . '.orm.factory');
                    $objectManagerId = 'doctrine.orm.' . $managerConfig['object_manager'] . '_entity_manager';
                    $auditObjectManagerId = 'doctrine.orm.' . $managerConfig['audit_object_manager'] . '_entity_manager';

                    break;
                default:
                    throw new RuntimeException('Unsupported type of driver "' . $managerConfig['driver'] . '"');
            }

            $configuration = new Definition($configurationClass);
            $container->setDefinition($configurationId, $configuration);
            $configuration->setArguments(array($managerConfig['options']));

            $objectManager = $container->get($objectManagerId);
            $driver = $objectManager->getConfiguration()->getMetadataDriverImpl();
            $auditDriver = $this->getAuditDriver($driver);

            $metadataFactoryClass = $container->getParameter(DreamCommerceObjectAuditExtension::ALIAS . '.metadata_factory.class');
            $metadataFactory = new Definition($metadataFactoryClass);
            $metadataFactory->setArguments(array(
                new Reference($objectManagerId),
                $auditDriver,
            ));

            $container->setDefinition($metadataFactoryId, $metadataFactory);

            $manager = new Definition($managerClass);
            $manager->setArguments(array(
                $configuration,
                new Reference($objectManagerId),
                new Reference(DreamCommerceObjectAuditExtension::ALIAS . '.revision_manager'),
                $auditFactory,
                $metadataFactory,
                new Reference($auditObjectManagerId),
            ));

            $container->setDefinition($managerId, $manager);

            $definition->addMethodCall(
                'registerObjectAuditManager',
                array(
                    $name,
                    new Reference($managerId),
                )
            );
        }

        $taggedServices = $container->findTaggedServiceIds(
            DreamCommerceObjectAuditExtension::ALIAS.'.manager'
        );

        foreach ($taggedServices as $id => $attributes) {
            foreach (array('name', 'object_manager') as $key) {
                if (!isset($attributes[$key])) {
                    throw new RuntimeException('Attribute "' . $key . '" is required');
                }
            }

            $name = $attributes['name'];
            $managerId = $attributes['object_manager'];

            $definition->addMethodCall(
                'registerObjectAuditManager',
                array(
                    $name,
                    new Reference($managerId)
                )
            );
        }

        $defaultManager = $container->getParameter(DreamCommerceObjectAuditExtension::ALIAS.'.default_manager');
        if (!empty($defaultManager)) {
            $container->setAlias(DreamCommerceObjectAuditExtension::ALIAS . '.manager', DreamCommerceObjectAuditExtension::ALIAS . '.' . $defaultManager . '_manager');
            $container->setAlias(DreamCommerceObjectAuditExtension::ALIAS . '.configuration', DreamCommerceObjectAuditExtension::ALIAS . '.' . $defaultManager . '_configuration');
            $container->setAlias(DreamCommerceObjectAuditExtension::ALIAS . '.metadata_factory', DreamCommerceObjectAuditExtension::ALIAS . '.' . $defaultManager . '_metadata_factory');
        }
    }

    public function getAuditDriver(MappingDriver $driver): Definition
    {
        $auditDriver = null;
        if ($driver instanceof MappingDriverChain) {
            $auditDriver = new Definition(AuditMappingDriverChain::class);
            foreach ($driver->getDrivers() as $namespace => $partDriver) {
                $auditPartDriver = $this->getAuditDriver($partDriver);
                $auditDriver->addMethodCall(
                    'addDriver',
                    array(
                        $auditPartDriver, $namespace
                    )
                );
            }
        } elseif ($driver instanceof AnnotationDriver) {
            $annotationReader = new Definition(AnnotationReader::class);
            $auditDriver = new Definition(AuditAnnotationDriver::class);
            $auditDriver->addArgument($annotationReader);
        } elseif ($driver instanceof FileDriver) {
            /** @var SymfonyFileLocator $locator */
            $locator = $driver->getLocator();
            $auditLocator = new Definition(SymfonyFileLocator::class);
            $paths = (array)$locator->getPaths();
            if ($locator instanceof SymfonyFileLocator) {
                $paths = $locator->getNamespacePrefixes();
            }

            $auditLocator->addArgument($paths);
            $auditLocator->addArgument($locator->getFileExtension());

            if ($driver instanceof XmlDriver) {
                $auditDriver = new Definition(AuditXmlDriver::class);
            } elseif ($driver instanceof YamlDriver) {
                $auditDriver = new Definition(AuditYamlDriver::class);
            }

            $auditDriver->addMethodCall(
                'setLocator',
                array($auditLocator)
            );
        }

        if ($auditDriver === null) {
            throw new RuntimeException('Unsupported type of driver "'.get_class($driver).'"');
        }

        return $auditDriver;
    }
}
