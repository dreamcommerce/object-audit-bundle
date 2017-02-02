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

namespace DreamCommerce\Tests\ObjectAuditBundle;

use DateTime;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types\RevisionEnumType;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types\RevisionUInt16Type;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types\RevisionUInt32Type;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types\RevisionUInt8Type;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types\UTCDateTimeType;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORM\Factory\ObjectAuditFactory;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORM\Subscriber\CreateSchemaSubscriber;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORM\Subscriber\LogRevisionsSubscriber;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORMAuditConfiguration;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORMAuditManager;
use DreamCommerce\Component\ObjectAudit\Factory\ObjectAuditFactoryInterface;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\Repository\RevisionRepositoryInterface;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\RevisionTest;
use Gedmo;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection|null
     */
    protected static $conn;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var SchemaTool
     */
    private $schemaTool;

    /**
     * @var ORMAuditManager
     */
    protected $auditManager;

    /**
     * @var ObjectAuditFactoryInterface
     */
    protected $objectAuditFactory;

    /**
     * @var ORMAuditConfiguration
     */
    protected $auditConfiguration;

    protected $customTypes = array();

    protected $schemaEntities = array();

    protected $auditedEntities = array();

    public function setUp()
    {
        $this->getEntityManager();
        $this->getSchemaTool();
        $this->getAuditManager();
        $this->setUpEntitySchema();
    }

    public function tearDown()
    {
        $this->tearDownEntitySchema();
        $this->em = null;
        $this->auditManager = null;
        $this->schemaTool = null;
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        if (null !== $this->em) {
            return $this->em;
        }

        $config = new Configuration();
        $config->setMetadataCacheImpl(new ArrayCache());
        $config->setQueryCacheImpl(new ArrayCache());
        $config->setProxyDir(__DIR__ . '/Proxies');
        $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_EVAL);
        $config->setProxyNamespace('DreamCommerce\Tests\ObjectAuditBundle\Proxies');

        $config->setMetadataDriverImpl($config->newDefaultAnnotationDriver(array(
            realpath(__DIR__ . '/Fixtures'),
            realpath(__DIR__ . '/Fixtures/Core'),
            realpath(__DIR__ . '/Fixtures/Issue'),
            realpath(__DIR__ . '/Fixtures/Relation'),
        ), false));

        Gedmo\DoctrineExtensions::registerAnnotations();

        $connection = $this->_getConnection();

        // get rid of more global state
        $evm = $connection->getEventManager();
        foreach ($evm->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $evm->removeEventListener(array($event), $listener);
            }
        }

        $this->em = EntityManager::create($connection, $config);

        if (isset($this->customTypes) and is_array($this->customTypes)) {
            foreach ($this->customTypes as $customTypeName => $customTypeClass) {
                if (!Type::hasType($customTypeName)) {
                    Type::addType($customTypeName, $customTypeClass);
                }
                $this->em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('db_' . $customTypeName, $customTypeName);
            }
        }

        return $this->em;
    }

    /**
     * @return SchemaTool
     */
    protected function getSchemaTool()
    {
        if (null !== $this->schemaTool) {
            return $this->schemaTool;
        }

        return $this->schemaTool = new SchemaTool($this->getEntityManager());
    }

    /**
     * @return Connection
     */
    protected function _getConnection()
    {
        if (!isset(self::$conn)) {
            if (isset(
                $GLOBALS['db_type'],
                $GLOBALS['db_username'],
                $GLOBALS['db_password'],
                $GLOBALS['db_host'],
                $GLOBALS['db_name'],
                $GLOBALS['db_port']
            )) {
                $params = array(
                    'driver' => $GLOBALS['db_type'],
                    'user' => $GLOBALS['db_username'],
                    'password' => $GLOBALS['db_password'],
                    'host' => $GLOBALS['db_host'],
                    'dbname' => $GLOBALS['db_name'],
                    'port' => $GLOBALS['db_port'],
                );

                $tmpParams = $params;
                $dbname = $params['dbname'];
                unset($tmpParams['dbname']);

                $conn = DriverManager::getConnection($tmpParams);
                $platform = $conn->getDatabasePlatform();

                if ($platform->supportsCreateDropDatabase()) {
                    $conn->getSchemaManager()->dropAndCreateDatabase($dbname);
                } else {
                    $sm = $conn->getSchemaManager();
                    $schema = $sm->createSchema();
                    $stmts = $schema->toDropSql($conn->getDatabasePlatform());
                    foreach ($stmts as $stmt) {
                        $conn->exec($stmt);
                    }
                }

                $conn->close();
            } else {
                $params = array(
                    'driver' => 'pdo_sqlite',
                    'memory' => true,
                );
            }

            self::$conn = DriverManager::getConnection($params);
        }

        return self::$conn;
    }

    protected function getObjectAuditFactory()
    {
        if($this->objectAuditFactory === null) {
            $this->objectAuditFactory = new ObjectAuditFactory();
        }

        return $this->objectAuditFactory;
    }

    /**
     * @return ORMAuditManager
     */
    protected function getAuditManager()
    {
        if ($this->auditManager !== null) {
            return $this->auditManager;
        }

        $revisionClass = RevisionTest::class;
        $configuration = $this->getAuditConfiguration($revisionClass);
        $revisionFactory = new Factory($revisionClass);
        $objectAuditFactory = $this->getObjectAuditFactory();
        $auditObjectManager = $defaultObjectManager = $this->getEntityManager();
        /** @var RevisionRepositoryInterface $revisionRepository */
        $revisionRepository = $auditObjectManager->getRepository($revisionClass);

        $auditManager = new ORMAuditManager(
            $configuration,
            $revisionFactory,
            $objectAuditFactory,
            $revisionRepository,
            $defaultObjectManager,
            $auditObjectManager
        );

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('get')
            ->will(
                $this->returnValueMap(
                    array(
                        array('dream_commerce_object_audit.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $auditManager)
                    )
                )
            );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturn(null);

        $connection = $defaultObjectManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $eventManager = $connection->getEventManager();

        $rtel = new ResolveTargetEntityListener();
        $rtel->addResolveTargetEntity(RevisionInterface::class, RevisionTest::class, array());

        $eventManager->addEventListener(Events::loadClassMetadata, $rtel);
        $eventManager->addEventSubscriber(new LogRevisionsSubscriber($container, $eventDispatcher));
        $eventManager->addEventSubscriber(new CreateSchemaSubscriber($container));

        $types = array(
            RevisionEnumType::TYPE_NAME => RevisionEnumType::class,
            RevisionUInt8Type::TYPE_NAME => RevisionUInt8Type::class,
            RevisionUInt16Type::TYPE_NAME => RevisionUInt16Type::class,
            RevisionUInt32Type::TYPE_NAME => RevisionUInt32Type::class,
            UTCDateTimeType::TYPE_NAME => UTCDateTimeType::class,
        );

        foreach ($types as $type => $className) {
            if (!Type::hasType($type)) {
                Type::addType($type, $className);
                $platform->registerDoctrineTypeMapping($type, $type);
            }
        }

        return $this->auditManager = $auditManager;
    }

    protected function getAuditConfiguration($revisionClass)
    {
        if ($this->auditConfiguration !== null) {
            return $this->auditConfiguration;
        }

        $auditConfig = new ORMAuditConfiguration($revisionClass);
        $auditConfig->setAuditedClasses($this->auditedEntities);
        $auditConfig->setGlobalIgnoreProperties(array('ignoreMe'));

        return $this->auditConfiguration = $auditConfig;
    }

    protected function setUpEntitySchema()
    {
        $em = $this->getEntityManager();
        $classes = array_map(function ($value) use ($em) {
            return $em->getClassMetadata($value);
        }, $this->schemaEntities);

        $this->getSchemaTool()->createSchema($classes);
    }

    protected function tearDownEntitySchema()
    {
        $em = $this->getEntityManager();
        $classes = array_map(function ($value) use ($em) {
            return $em->getClassMetadata($value);
        }, $this->schemaEntities);

        $auditTables = array();
        $auditManager = $this->getAuditManager();
        $configuration = $auditManager->getConfiguration();

        /** @var ClassMetadata $classMetadata */
        foreach($classes as $classMetadata) {
            if($configuration->isClassAudited($classMetadata->name)) {
                $auditTables[] = $auditManager->getAuditTableNameForClass($classMetadata->name);
                if($classMetadata->name != $classMetadata->rootEntityName) {
                    $auditTables[] = $auditManager->getAuditTableNameForClass($classMetadata->rootEntityName);
                }
            }
        }

        $auditTables = array_unique($auditTables);

        $this->getSchemaTool()->dropSchema($classes);
        $sm = $this->em->getConnection()->getSchemaManager();
        foreach($auditTables as $auditTable) {
            if($sm->tablesExist($auditTable)) {
                $sm->dropTable($auditTable);
            }
        }
    }

    protected function getRevision(int $id)
    {
        $repository = $this->em->getRepository(RevisionTest::class);
        return $repository->find($id);
    }
}
