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

namespace DreamCommerce\Tests\ObjectAuditBundle\DependencyInjection;

use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\DreamCommerceObjectAuditExtension;
use DreamCommerce\Component\ObjectAudit\Model\Revision;
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
                        'model' => Revision::class,
                    )
                ),
            )
        ));

        $this->assertContainerBuilderHasService('dream_commerce.factory.revision');
        $this->assertContainerBuilderHasService('dream_commerce.repository.revision');
        $this->assertContainerBuilderHasService('dream_commerce.manager.revision');
        $this->assertContainerBuilderHasParameter('dream_commerce.model.revision.class', Revision::class);
        $this->assertContainerBuilderHasParameter(DreamCommerceObjectAuditExtension::ALIAS . '.managers');
    }

    /**
     * {@inheritdoc}
     */
    protected function getContainerExtensions()
    {
        return array(
            new DreamCommerceObjectAuditExtension(),
        );
    }
}
