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

namespace DreamCommerce\Tests\ObjectAudit;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use DreamCommerce\Component\ObjectAudit\Configuration\ORMAuditConfiguration;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionEnumType;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionUInt16Type;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionUInt32Type;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionUInt8Type;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\UTCDateTimeType;
use DreamCommerce\Component\ObjectAudit\Doctrine\ORM\Subscriber\CreateSchemaSubscriber;
use DreamCommerce\Component\ObjectAudit\Doctrine\ORM\Subscriber\LogRevisionsSubscriber;
use DreamCommerce\Component\ObjectAudit\Factory\ORMObjectAuditFactory;
use DreamCommerce\Component\ObjectAudit\Manager\ORMAuditManager;
use DreamCommerce\Component\ObjectAudit\Manager\ORMRevisionManager;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\AnnotationDriver;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadataFactory;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ObjectAuditRegistry;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Common\RevisionTest;
use Gedmo;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection|null
     */
    protected static $connection;

    /**
     * @var Connection|null
     */
    protected static $auditConnection;

    /**
     * @var SchemaTool
     */
    private $schemaTool;

    /**
     * @var SchemaTool
     */
    private $auditSchemaTool;

    /**
     * @var EntityManager
     */
    protected $persistManager;

    /**
     * @var EntityManager
     */
    protected $auditPersistManager;

    /**
     * @var ORMRevisionManager
     */
    protected $revisionManager;

    /**
     * @var ObjectAuditRegistry
     */
    protected $objectAuditRegistry;

    /**
     * @var ORMAuditManager
     */
    protected $objectAuditManager;

    /**
     * @var ORMObjectAuditFactory
     */
    protected $objectAuditFactory;

    /**
     * @var ORMAuditConfiguration
     */
    protected $auditConfiguration;

    /**
     * @var ObjectAuditMetadataFactory
     */
    protected $objectAuditMetadataFactory;

    /**
     * @var AnnotationDriver
     */
    protected $objectAuditMetadataDriver;

    /**
     * @var array
     */
    protected $customTypes = array();

    /**
     * @var string
     */
    protected $fixturesPath;

    public function setUp()
    {
        $this->getObjectAuditManager();

        $this->setUpTestSchema();
        $this->setUpAuditSchema();
    }

    public function tearDown()
    {
        $this->tearDownTestSchema();
        $this->tearDownAuditSchema();

        $this->persistManager = null;
        $this->auditPersistManager = null;
        $this->objectAuditManager = null;
        $this->objectAuditRegistry = null;
        $this->auditConfiguration = null;
        $this->objectAuditFactory = null;
        $this->revisionManager = null;
        $this->objectAuditMetadataDriver = null;
        $this->schemaTool = null;
        $this->auditSchemaTool = null;
    }

    /**
     * @return EntityManager
     */
    protected function getPersistManager()
    {
        if (null !== $this->persistManager) {
            return $this->persistManager;
        }

        $config = new Configuration();
        $config->setMetadataCacheImpl(new ArrayCache());
        $config->setQueryCacheImpl(new ArrayCache());
        $config->setProxyDir(__DIR__ . '/Proxies');
        $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_EVAL);
        $config->setProxyNamespace('DreamCommerce\Tests\ObjectAuditBundle\Proxies');

        $paths = array($this->fixturesPath);
        $paths[] = realpath(__DIR__ . '/Fixtures/Common');
        $config->setMetadataDriverImpl($config->newDefaultAnnotationDriver($paths, false));

        Gedmo\DoctrineExtensions::registerAnnotations();

        $connection = $this->getTestConnection();
        // get rid of more global state
        $evm = $connection->getEventManager();
        foreach ($evm->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $evm->removeEventListener(array($event), $listener);
            }
        }

        $this->persistManager = EntityManager::create($connection, $config);

        if (isset($this->customTypes) and is_array($this->customTypes)) {
            foreach ($this->customTypes as $customTypeName => $customTypeClass) {
                if (!Type::hasType($customTypeName)) {
                    Type::addType($customTypeName, $customTypeClass);
                }
                $this->persistManager->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('db_' . $customTypeName, $customTypeName);
            }
        }

        return $this->persistManager;
    }

    /**
     * @return SchemaTool
     */
    protected function getSchemaTool()
    {
        if (null !== $this->schemaTool) {
            return $this->schemaTool;
        }

        return $this->schemaTool = new SchemaTool($this->getPersistManager());
    }

    /**
     * @return SchemaTool
     */
    protected function getAuditSchemaTool()
    {
        if (null !== $this->auditSchemaTool) {
            return $this->auditSchemaTool;
        }

        return $this->auditSchemaTool = new SchemaTool($this->getAuditPersistManager());
    }

    protected function getConnectionParams()
    {
        $params = null;
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
        }

        return $params;
    }

    protected function getTestConnection(): Connection
    {
        if(self::$connection === null) {
            $params = $this->getConnectionParams();
            self::$connection = $this->getConnection($params);
        }

        return self::$connection;
    }

    protected function getAuditConnection(): Connection
    {
        if(self::$auditConnection === null) {
            $params = $this->getConnectionParams();
            if(is_array($params) && isset($params['dbname'])) {
                $params['dbname'] .= '_audit';
            }

            self::$auditConnection = $this->getConnection($params);
        }

        return self::$auditConnection;
    }

    /**
     * @param array|null $params
     * @return Connection
     */
    protected function getConnection(array $params = null): Connection
    {
        if (is_array($params) && count($params) > 0) {
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

        return DriverManager::getConnection($params);
    }



    protected function getAuditPersistManager(): EntityManager
    {
        if($this->auditPersistManager !== null) {
            return $this->auditPersistManager;
        }

        $config = new Configuration();
        $config->setMetadataCacheImpl(new ArrayCache());
        $config->setQueryCacheImpl(new ArrayCache());
        $config->setProxyDir(__DIR__ . '/Proxies/Audit');
        $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_EVAL);
        $config->setProxyNamespace('DreamCommerce\Tests\ObjectAuditBundle\Audit\Proxies');

        $paths = array(realpath(__DIR__ . '/Fixtures/Common'));
        $config->setMetadataDriverImpl($config->newDefaultAnnotationDriver($paths, false));

        $connection = $this->getAuditConnection();
        $this->auditPersistManager = EntityManager::create($connection, $config);

        return $this->auditPersistManager;
    }

    protected function getRevisionManager(): ORMRevisionManager
    {
        if($this->revisionManager === null) {
            $revisionClass = RevisionTest::class;
            $revisionFactory = new Factory($revisionClass);
            $auditPersistManager = $this->getAuditPersistManager();
            $revisionRepository = $auditPersistManager->getRepository($revisionClass);

            $this->revisionManager = new ORMRevisionManager(
                $revisionClass,
                $auditPersistManager,
                $revisionFactory,
                $revisionRepository
            );
        }

        return $this->revisionManager;
    }

    protected function getObjectAuditFactory(): ORMObjectAuditFactory
    {
        if($this->objectAuditFactory === null) {
            $this->objectAuditFactory = new ORMObjectAuditFactory($this->getRevisionManager());
        }

        return $this->objectAuditFactory;
    }

    protected function getObjectAuditMetadataDriver(): AnnotationDriver
    {
        if($this->objectAuditMetadataDriver === null) {
            $this->objectAuditMetadataDriver = AnnotationDriver::create();
        }

        return $this->objectAuditMetadataDriver;
    }

    protected function getObjectAuditMetadataFactory(): ObjectAuditMetadataFactory
    {
        if($this->objectAuditMetadataFactory === null) {
            $this->objectAuditMetadataFactory = new ObjectAuditMetadataFactory(
                $this->getPersistManager(),
                $this->getObjectAuditMetadataDriver()
            );
        }

        return $this->objectAuditMetadataFactory;
    }

    protected function getObjectAuditRegistry()
    {
        if($this->objectAuditRegistry === null) {
            $this->objectAuditRegistry = new ObjectAuditRegistry();
        }

        return $this->objectAuditRegistry;
    }

    /**
     * @return ORMAuditManager
     */
    protected function getObjectAuditManager()
    {
        if ($this->objectAuditManager !== null) {
            return $this->objectAuditManager;
        }

        $configuration = $this->getAuditConfiguration();
        $persistManager = $this->getPersistManager();
        $revisionManager = $this->getRevisionManager();
        $objectAuditFactory = $this->getObjectAuditFactory();
        $objectAuditMetadataFactory = $this->getObjectAuditMetadataFactory();
        $objectAuditRegistry = $this->getObjectAuditRegistry();

        $objectAuditManager = new ORMAuditManager(
            $configuration,
            $persistManager,
            $revisionManager,
            $objectAuditFactory,
            $objectAuditMetadataFactory
        );

        $objectAuditRegistry->registerObjectAuditManager('default', $persistManager, $objectAuditManager);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturn(null);

        $connection = $persistManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $eventManager = $connection->getEventManager();

        $rtel = new ResolveTargetEntityListener();
        $rtel->addResolveTargetEntity(RevisionInterface::class, RevisionTest::class, array());

        $eventManager->addEventListener(Events::loadClassMetadata, $rtel);
        $eventManager->addEventSubscriber(new LogRevisionsSubscriber($objectAuditRegistry, 'default'));
        $eventManager->addEventSubscriber(new CreateSchemaSubscriber($objectAuditRegistry, 'default'));

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

        return $this->objectAuditManager = $objectAuditManager;
    }

    protected function getAuditConfiguration(): ORMAuditConfiguration
    {
        if ($this->auditConfiguration !== null) {
            return $this->auditConfiguration;
        }

        $auditConfig = new ORMAuditConfiguration();
        $auditConfig->setGlobalIgnoreProperties(array('globalIgnoreMe'));
        $auditConfig->setObjectMetadataDriver($this->getObjectAuditMetadataDriver());
        $auditConfig->setRevisionTypeFieldType(RevisionUInt16Type::TYPE_NAME);

        return $this->auditConfiguration = $auditConfig;
    }

    protected function setUpTestSchema()
    {
        $classes = $this->getPersistManager()->getMetadataFactory()->getAllMetadata();
        $this->getSchemaTool()->createSchema($classes);
    }

    protected function setUpAuditSchema()
    {
        $classes = $this->getAuditPersistManager()->getMetadataFactory()->getAllMetadata();
        $this->getAuditSchemaTool()->createSchema($classes);
    }

    protected function tearDownTestSchema()
    {
        $classes = $this->getPersistManager()->getMetadataFactory()->getAllMetadata();
        $this->getSchemaTool()->dropSchema($classes);
    }

    protected function tearDownAuditSchema()
    {
        $sm = $this->auditPersistManager->getConnection()->getSchemaManager();
        foreach($sm->listTableNames() as $auditTable) {
            $sm->dropTable($auditTable);
        }
    }

    protected function getRevision(int $id)
    {
        $repository = $this->auditPersistManager->getRepository(RevisionTest::class);
        return $repository->find($id);
    }
}
