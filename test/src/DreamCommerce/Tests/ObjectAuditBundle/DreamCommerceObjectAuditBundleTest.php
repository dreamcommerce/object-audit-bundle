<?php

namespace DreamCommerce\Tests\ObjectAuditBundle;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DreamCommerceObjectAuditBundleTest extends WebTestCase
{
    /**
     * @test
     */
    public function its_services_are_intitializable()
    {
        /** @var ContainerInterface $container */
        $container = self::createClient()->getContainer();

        $services = $container->getServiceIds();

        $services = array_filter($services, function ($serviceId) {
            return false !== strpos($serviceId, 'dream_commerce_object_audit');
        });

        $this->assertTrue(count($services) > 0);

        foreach ($services as $id) {
            $container->get($id);
        }
    }
}