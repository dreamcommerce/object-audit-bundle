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

namespace DreamCommerce\Tests\ObjectAuditBundle\DependencyInjection\Compiler;

use Doctrine\Persistence\Mapping\Driver\AnnotationDriver;
use Doctrine\Persistence\Mapping\Driver\DefaultFileLocator;
use Doctrine\Persistence\Mapping\Driver\FileDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Compiler\ManagerCompilerPass;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Configuration\ORMConfiguration;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\DreamCommerceObjectAuditExtension;
use DreamCommerce\Component\ObjectAudit\Factory\ORMObjectAuditFactory;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Manager\ORMAuditManager;
use DreamCommerce\Component\ObjectAudit\Manager\RevisionManagerInterface;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\AnnotationDriver as AnnotationAuditDriver;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\MappingDriverChain as MappingAuditDriverChain;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\XmlDriver as XmlAuditDriver;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\YamlDriver as YamlAuditDriver;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadataFactory;
use DreamCommerce\Component\ObjectAudit\ObjectAuditRegistry;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;

class ManagerCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function setUp()
    {
        parent::setUp();

        $definition = new Definition(EntityManager::class);
        $definition->setAbstract(true);

        $this->container->setDefinition('doctrine.orm.entity_manager.abstract', $definition);

        $this->registerService(DreamCommerceObjectAuditExtension::ALIAS . '.registry', ObjectAuditRegistry::class);
        $this->registerService(DreamCommerceObjectAuditExtension::ALIAS . '.orm.factory', ORMObjectAuditFactory::class);
        $this->registerService(DreamCommerceObjectAuditExtension::ALIAS . '.revision_manager', RevisionManagerInterface::class);

        $managers = array();
        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.default_manager', null);
        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.managers', $managers);

        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.orm.configuration.class', ORMConfiguration::class);
        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.orm.manager.class', ORMAuditManager::class);
        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.orm.factory.class', ORMObjectAuditFactory::class);
        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.metadata_factory.class', ObjectAuditMetadataFactory::class);
    }

    public function testEmptyObjectAuditManagers()
    {
        $this->compile();

        $this->assertContainerBuilderNotHasService(DreamCommerceObjectAuditExtension::ALIAS . '.manager');
        $this->assertContainerBuilderNotHasService(DreamCommerceObjectAuditExtension::ALIAS . '.configuration');
        $this->assertContainerBuilderNotHasService(DreamCommerceObjectAuditExtension::ALIAS . '.metadata_factory');
    }

    public function testSingleObjectAuditManager()
    {
        $options = array(
            'ignore_properties' => array(
                'globalIgnoreProperty'
            ),
            'table_prefix' => 'audit_'
        );
        $managers = array(
            'baz' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'foo',
                'audit_object_manager' => 'foo_audit',
                'options' => $options
            )
        );

        $this->registerEntityMockManager('foo');
        $this->registerEntityMockManager('foo_audit');

        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.default_manager', 'baz');
        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.managers', $managers);
        $this->compile();

        $this->assertContainerBuilderHasService(DreamCommerceObjectAuditExtension::ALIAS . '.baz_manager');
        $this->assertContainerBuilderHasService(DreamCommerceObjectAuditExtension::ALIAS . '.baz_configuration');
        $this->assertContainerBuilderHasAlias(DreamCommerceObjectAuditExtension::ALIAS . '.manager', DreamCommerceObjectAuditExtension::ALIAS . '.baz_manager');
        $this->assertContainerBuilderHasAlias(DreamCommerceObjectAuditExtension::ALIAS . '.configuration', DreamCommerceObjectAuditExtension::ALIAS . '.baz_configuration');
        $this->assertContainerBuilderHasAlias(DreamCommerceObjectAuditExtension::ALIAS . '.metadata_factory', DreamCommerceObjectAuditExtension::ALIAS . '.baz_metadata_factory');

        $configuration = $this->container->getDefinition(DreamCommerceObjectAuditExtension::ALIAS . '.baz_configuration');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DreamCommerceObjectAuditExtension::ALIAS . '.baz_configuration', 0, $options);

        $objectManager = new Reference('doctrine.orm.foo_entity_manager');
        $auditObjectManager = new Reference('doctrine.orm.foo_audit_entity_manager');
        $metadataFactory = $this->container->getDefinition(DreamCommerceObjectAuditExtension::ALIAS . '.baz_metadata_factory');
        $revisionManager = new Reference(DreamCommerceObjectAuditExtension::ALIAS . '.revision_manager');
        $auditFactory = new Reference(DreamCommerceObjectAuditExtension::ALIAS . '.orm.factory');

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DreamCommerceObjectAuditExtension::ALIAS . '.baz_manager', 0, $configuration);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DreamCommerceObjectAuditExtension::ALIAS . '.baz_manager', 1, $objectManager);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DreamCommerceObjectAuditExtension::ALIAS . '.baz_manager', 2, $revisionManager);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DreamCommerceObjectAuditExtension::ALIAS . '.baz_manager', 3, $auditFactory);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DreamCommerceObjectAuditExtension::ALIAS . '.baz_manager', 4, $metadataFactory);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(DreamCommerceObjectAuditExtension::ALIAS . '.baz_manager', 5, $auditObjectManager);
    }

    public function testMultipleObjectAuditManagers()
    {
        $managers = array(
            'baz' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'foo',
                'audit_object_manager' => 'foo_audit',
                'options' => array()
            ),
            'bar' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'bar',
                'audit_object_manager' => 'bar_audit',
                'options' => array()
            )
        );

        $this->registerEntityMockManager('foo');
        $this->registerEntityMockManager('foo_audit');
        $this->registerEntityMockManager('bar');
        $this->registerEntityMockManager('bar_audit');

        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.default_manager', 'bar');
        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.managers', $managers);
        $this->compile();

        $this->assertContainerBuilderHasService(DreamCommerceObjectAuditExtension::ALIAS . '.baz_manager');
        $this->assertContainerBuilderHasService(DreamCommerceObjectAuditExtension::ALIAS . '.baz_configuration');
        $this->assertContainerBuilderHasService(DreamCommerceObjectAuditExtension::ALIAS . '.baz_metadata_factory');

        $this->assertContainerBuilderHasService(DreamCommerceObjectAuditExtension::ALIAS . '.bar_manager');
        $this->assertContainerBuilderHasService(DreamCommerceObjectAuditExtension::ALIAS . '.bar_configuration');
        $this->assertContainerBuilderHasService(DreamCommerceObjectAuditExtension::ALIAS . '.bar_metadata_factory');

        $this->assertContainerBuilderHasAlias(DreamCommerceObjectAuditExtension::ALIAS . '.manager', DreamCommerceObjectAuditExtension::ALIAS . '.bar_manager');
        $this->assertContainerBuilderHasAlias(DreamCommerceObjectAuditExtension::ALIAS . '.configuration', DreamCommerceObjectAuditExtension::ALIAS . '.bar_configuration');
        $this->assertContainerBuilderHasAlias(DreamCommerceObjectAuditExtension::ALIAS . '.metadata_factory', DreamCommerceObjectAuditExtension::ALIAS . '.bar_metadata_factory');
    }

    public function testObjectAuditManagerMetadataDrivers()
    {
        $managers = array(
            'xml' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'xml',
                'audit_object_manager' => 'xml',
                'options' => array()
            ),
            'yaml' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'yaml',
                'audit_object_manager' => 'yaml',
                'options' => array()
            ),
            'annotation' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'annotation',
                'audit_object_manager' => 'annotation',
                'options' => array()
            ),
            'chain' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'chain',
                'audit_object_manager' => 'chain',
                'options' => array()
            )
        );

        $drivers = array(
            'xml' => XmlAuditDriver::class,
            'yaml' => YamlAuditDriver::class,
            'annotation' => AnnotationAuditDriver::class,
            'chain' => MappingAuditDriverChain::class
        );

        $this->registerEntityMockManager('xml', XmlDriver::class);
        $this->registerEntityMockManager('yaml', YamlDriver::class);
        $this->registerEntityMockManager('annotation', AnnotationDriver::class);
        $this->registerEntityMockManager('chain', MappingDriverChain::class);

        $this->setParameter(DreamCommerceObjectAuditExtension::ALIAS . '.managers', $managers);
        $this->compile();

        foreach ($managers as $key => $value) {
            $metadataFactory = $this->container->getDefinition(DreamCommerceObjectAuditExtension::ALIAS . '.' . $key . '_metadata_factory');
            $objectManager = new Reference('doctrine.orm.' . $key . '_entity_manager');

            $this->assertContainerBuilderHasServiceDefinitionWithArgument(DreamCommerceObjectAuditExtension::ALIAS . '.' . $key . '_metadata_factory', 0, $objectManager);

            /** @var Definition $argument */
            $argument = $metadataFactory->getArgument(1);
            $this->assertInstanceOf(Definition::class, $argument);
            $this->assertEquals($drivers[$key], $argument->getClass());
        }
    }

    protected function registerCompilerPass(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ManagerCompilerPass(), PassConfig::TYPE_REMOVE);
    }

    protected function registerEntityMockManager(string $name, string $driverClass = null)
    {
        if ($driverClass === null) {
            $driverClass = AnnotationDriver::class;
        }

        $configObjectManager = $this->getMockBuilder(Configuration::class)
            ->setMethods(array('getMetadataDriverImpl'))
            ->getMock();

        $driver = $this->getMockBuilder($driverClass)
            ->disableOriginalConstructor()
            ->setMethods(array('getLocator', 'getDrivers', 'loadMetadataForClass'))
            ->getMock();

        if ($driver instanceof FileDriver) {
            $locator = $this->createMock(DefaultFileLocator::class);
            $driver->expects($this->any())
                ->method('getLocator')
                ->willReturn($locator);
        }
        if ($driver instanceof MappingDriverChain) {
            $driver->expects($this->any())
                ->method('getDrivers')
                ->willReturn(array());
        }

        $configObjectManager->expects($this->any())
            ->method('getMetadataDriverImpl')
            ->willReturn($driver);

        $fooObjectManager = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getConfiguration'))
            ->getMock();

        $fooObjectManager->expects($this->any())
            ->method('getConfiguration')
            ->willReturn($configObjectManager);

        $fooAuditObjectManager = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $fooAuditObjectManager->expects($this->any())
            ->method('getConfiguration')
            ->willReturn($configObjectManager);

        $this->container->set('doctrine.orm.' . $name . '_entity_manager', $fooObjectManager);
    }
}
