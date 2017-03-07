<?php

namespace DreamCommerce\Tests\ObjectAuditBundle;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\DreamCommerceObjectAuditExtension;
use DreamCommerce\Bundle\ObjectAuditBundle\Metadata\ResourceAuditMetadataFactory;
use DreamCommerce\Component\ObjectAudit\Manager\ResourceAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadata;
use DreamCommerce\Component\ObjectAudit\Metadata\ResourceAuditMetadata;
use DreamCommerce\Fixtures\ObjectAuditBundle\Entity\AuditResource;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ResourceAuditManagerTest extends WebTestCase
{
    /**
     * @var ResourceAuditManagerInterface
     */
    private $resourceManager;

    /**
     * @var ResourceAuditMetadataFactory
     */
    private $resourceMetadataFactory;

    public function setUp()
    {
        parent::setUp();

        /** @var ContainerInterface $container */
        $container = self::createClient()->getContainer();
        $this->resourceManager = $container->get(DreamCommerceObjectAuditExtension::ALIAS . '.resource_manager');
        $this->resourceMetadataFactory = $this->resourceManager->getMetadataFactory();
    }

    public function testGetAllNames()
    {
        $this->assertEquals(array('dream_commerce.test_audit'), $this->resourceMetadataFactory->getAllNames());
    }

    public function testIsAudited()
    {
        $this->assertTrue($this->resourceMetadataFactory->isAudited('dream_commerce.test_audit'));
        $this->assertFalse($this->resourceMetadataFactory->isAudited('dream_commerce.test_not_audit'));
    }

    public function testMetadataFor()
    {
        $this->assertNull($this->resourceMetadataFactory->getMetadataFor('dream_commerce.test_not_audit'));
        $resourceMetadata = $this->resourceMetadataFactory->getMetadataFor('dream_commerce.test_audit');

        $this->assertInstanceOf(ResourceAuditMetadata::class, $resourceMetadata);
        $this->assertEquals('dream_commerce.test_audit', $resourceMetadata->resourceName);
        $this->assertInstanceOf(ObjectAuditMetadata::class, $resourceMetadata->objectAuditMetadata);
        $objectAuditMetadata = $resourceMetadata->objectAuditMetadata;
        $this->assertInstanceOf(ClassMetadata::class, $objectAuditMetadata->classMetadata);
        $this->assertEquals(AuditResource::class, $objectAuditMetadata->classMetadata->getName());
    }
}