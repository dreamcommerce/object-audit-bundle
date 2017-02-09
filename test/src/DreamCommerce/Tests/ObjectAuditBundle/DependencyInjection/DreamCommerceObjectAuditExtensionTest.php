<?php

namespace DreamCommerce\Tests\ObjectAuditBundle\DependencyInjection;

use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\DreamCommerceObjectAuditExtension;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Common\RevisionTest;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

class DreamCommerceObjectAuditExtensionTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function it_registers_services_and_parameters_for_resources()
    {
        $this->setParameter('kernel.bundles', array());
        $this->load(array(
            'resources' => array(
                'revision' => array(
                    'classes' => array(
                        'model' => RevisionTest::class,
                    )
                ),
            )
        ));

        $this->assertContainerBuilderHasService('dream_commerce.factory.revision');
        $this->assertContainerBuilderHasService('dream_commerce.repository.revision');
        $this->assertContainerBuilderHasService('dream_commerce.manager.revision');
        $this->assertContainerBuilderHasParameter('dream_commerce.model.revision.class', RevisionTest::class);
        $this->assertContainerBuilderHasParameter(DreamCommerceObjectAuditExtension::ALIAS . '.managers');
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerExtensions()
    {
        return [
            new DreamCommerceObjectAuditExtension(),
        ];
    }
}