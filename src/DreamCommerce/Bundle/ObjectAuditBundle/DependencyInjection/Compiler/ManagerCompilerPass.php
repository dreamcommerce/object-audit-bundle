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
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\DreamCommerceObjectAuditExtension;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\AnnotationDriver as AuditAnnotationDriver;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\MappingDriverChain as AuditMappingDriverChain;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\XmlDriver as AuditXmlDriver;
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

        foreach ($container->getParameter(DreamCommerceObjectAuditExtension::ALIAS.'.managers') as $name => $manager) {
            $managerId = DreamCommerceObjectAuditExtension::ALIAS.'.'. $name .'_manager';
            $configurationId = DreamCommerceObjectAuditExtension::ALIAS.'.' . $name . '_configuration';

            switch ($manager['driver']) {
                case ObjectAuditManagerInterface::DRIVER_ORM:
                    $configurationClass = $container->getParameter('dream_commerce_object_audit.orm.configuration.class');
                    $managerClass = $container->getParameter('dream_commerce_object_audit.orm.manager.class');
                    $auditFactory = $container->getDefinition('dream_commerce_object_audit.orm.factory');
                    $objectManagerId = 'doctrine.orm.' . $manager['object_manager'] . '_entity_manager';
                    $auditObjectManagerId = 'doctrine.orm.' . $manager['audit_object_manager'] . '_entity_manager';

                    break;
                default:
                    throw new RuntimeException();
            }

            $configuration = new Definition($configurationClass);
            $container->setDefinition($configurationId, $configuration);

            $objectManager = $container->get($objectManagerId);
            $driver = $objectManager->getConfiguration()->getMetadataDriverImpl();
            $auditDriver = $this->getAuditDriver($driver);

            $metadataFactoryClass = $container->getParameter('dream_commerce_object_audit.metadata_factory.class');
            $metadataFactory = new Definition($metadataFactoryClass);
            $metadataFactory->setArguments(array(
                new Reference($objectManagerId),
                $auditDriver,
            ));

            $managerDefinition = new Definition($managerClass);
            $managerDefinition->setArguments(array(
                $configuration,
                new Reference($objectManagerId),
                new Reference($auditObjectManagerId),
                $container->getDefinition('dream_commerce_object_audit.revision_manager'),
                $auditFactory,
                $metadataFactory,
            ));

            $container->setDefinition($managerId, $managerDefinition);

            $definition->addMethodCall(
                'registerObjectAuditManager',
                array(
                    $name,
                    new Reference($managerId),
                )
            );
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
        } elseif ($driver instanceof XmlDriver) {
            $locator = new Definition(DefaultFileLocator::class);
            $locator->addArgument((array)$driver->getLocator()->getPaths());
            $locator->addArgument($driver->getLocator()->getFileExtension());

            $auditDriver = new Definition(AuditXmlDriver::class);
            $auditDriver->addMethodCall(
                'setLocator',
                array($locator)
            );
        } else {
            throw new RuntimeException('Unsupported type of driver "'.get_class($driver).'"');
        }

        return $auditDriver;
    }
}
