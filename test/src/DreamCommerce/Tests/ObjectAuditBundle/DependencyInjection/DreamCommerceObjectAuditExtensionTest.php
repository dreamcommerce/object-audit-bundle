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
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Model\Revision;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

class DreamCommerceObjectAuditExtensionTest extends AbstractExtensionTestCase
{
    public function testEmptyConfig()
    {
        $this->load();

        $services = array(
            DreamCommerceObjectAuditExtension::ALIAS . '.manager',
            DreamCommerceObjectAuditExtension::ALIAS . '.configuration',
            DreamCommerceObjectAuditExtension::ALIAS . '.default_manager',
            DreamCommerceObjectAuditExtension::ALIAS . '.default_configuration'
        );

        foreach ($services as $id) {
            $this->assertContainerBuilderNotHasService($id);
        }

        $this->assertContainerBuilderHasParameter(DreamCommerceObjectAuditExtension::ALIAS . '.managers', array());
        $this->assertContainerBuilderHasParameter(DreamCommerceObjectAuditExtension::ALIAS . '.default_manager');

        $this->assertContainerBuilderHasService('dream_commerce.factory.revision');
        $this->assertContainerBuilderHasService('dream_commerce.repository.revision');
        $this->assertContainerBuilderHasService('dream_commerce.manager.revision');
        $this->assertContainerBuilderHasParameter('dream_commerce.model.revision.class', Revision::class);
    }

    public function testSingleObjectAuditManager()
    {
        $managers = array(
            'baz' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'foo',
                'audit_object_manager' => 'foo_audit'
            )
        );

        $this->load(array(
            'managers' => $managers
        ));

        $this->assertObjectAuditManagersParameter($managers);
        $this->assertContainerBuilderHasParameter(DreamCommerceObjectAuditExtension::ALIAS . '.default_manager', 'baz');
    }

    public function testOnlyObjectManager()
    {
        $managers = array(
            'baz' => array(
                'object_manager' => 'foo'
            )
        );

        $this->load(array(
            'managers' => $managers
        ));

        $this->assertObjectAuditManagersParameter($managers);
        $this->assertContainerBuilderHasParameter(DreamCommerceObjectAuditExtension::ALIAS . '.default_manager', 'baz');
    }

    public function testMultipleObjectAuditManagers()
    {
        $managers = array(
            'baz' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'foo',
                'audit_object_manager' => 'foo_audit'
            ),
            'bar' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'bar',
                'audit_object_manager' => 'bar_audit'
            )
        );

        $this->load(array(
            'managers' => $managers
        ));

        $this->assertObjectAuditManagersParameter($managers);
        $this->assertContainerBuilderHasParameter(DreamCommerceObjectAuditExtension::ALIAS . '.default_manager', 'baz');
    }

    public function testDefaultObjectAuditManager()
    {
        $managers = array(
            'baz' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'foo',
                'audit_object_manager' => 'foo_audit'
            ),
            'bar' => array(
                'driver' => ObjectAuditManagerInterface::DRIVER_ORM,
                'object_manager' => 'bar',
                'audit_object_manager' => 'bar_audit'
            )
        );

        $this->load(array(
            'default_manager' => 'bar',
            'managers' => $managers
        ));

        $this->assertObjectAuditManagersParameter($managers);
        $this->assertContainerBuilderHasParameter(DreamCommerceObjectAuditExtension::ALIAS . '.default_manager', 'bar');
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

    protected function assertObjectAuditManagersParameter(array $managers)
    {
        foreach ($managers as $k => $v) {
            if (!isset($managers[$k]['audit_object_manager'])) {
                $managers[$k]['audit_object_manager'] = $managers[$k]['object_manager'];
            }
            if (!isset($managers[$k]['driver'])) {
                $managers[$k]['driver'] = ObjectAuditManagerInterface::DRIVER_ORM;
            }
            if (!isset($managers[$k]['ignore_properties'])) {
                $managers[$k]['ignore_properties'] = array();
            }

            if ($managers[$k]['driver'] === ObjectAuditManagerInterface::DRIVER_ORM) {
                $managers[$k]['table_prefix'] = '';
                $managers[$k]['table_suffix'] = '_audit';
                $managers[$k]['revision_id_field_prefix'] = 'revision_';
                $managers[$k]['revision_id_field_suffix'] = '';
                $managers[$k]['revision_type_field_name'] = 'revision_type';
                $managers[$k]['revision_type_field_type'] = 'enumRevisionUInt16Type';
            }
        }

        $this->assertContainerBuilderHasParameter(DreamCommerceObjectAuditExtension::ALIAS . '.managers', $managers);
    }
}
