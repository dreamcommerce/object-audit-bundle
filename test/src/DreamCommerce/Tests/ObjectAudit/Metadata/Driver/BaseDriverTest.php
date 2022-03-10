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

namespace DreamCommerce\Tests\ObjectAudit\Metadata\Driver;

use Doctrine\Persistence\Mapping\Driver\FileLocator;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\DriverInterface;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\XmlDriver;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadata;
use DreamCommerce\Fixtures\ObjectAuditBundle\Entity\AuditEntity;
use DreamCommerce\Fixtures\ObjectAuditBundle\Entity\AuditEntityExtendsAuditParent;
use DreamCommerce\Fixtures\ObjectAuditBundle\Entity\AuditEntityExtendsNotAuditParent;
use DreamCommerce\Fixtures\ObjectAuditBundle\Entity\AuditParent;
use DreamCommerce\Fixtures\ObjectAuditBundle\Entity\NotAuditEntity;
use DreamCommerce\Fixtures\ObjectAuditBundle\Entity\NotAuditEntityExtendsAuditParent;
use DreamCommerce\Fixtures\ObjectAuditBundle\Entity\NotAuditParent;

abstract class BaseDriverTest extends \PHPUnit_Framework_TestCase
{
    public function testNotAuditEntity()
    {
        $driver = $this->getDriver(array(
            NotAuditEntity::class
        ));

        $this->assertFalse($driver->isTransient(NotAuditEntity::class));
        $objectAuditMetadata = $this->getObjectAuditMetadata();

        $driver->loadMetadataForClass(NotAuditEntity::class, $objectAuditMetadata);
        $this->assertTrue(is_array($objectAuditMetadata->ignoredProperties));
        $this->assertEmpty($objectAuditMetadata->ignoredProperties);
    }

    public function testAuditEntity()
    {
        $driver = $this->getDriver(array(
            AuditEntity::class
        ));

        $this->assertTrue($driver->isTransient(AuditEntity::class));

        $objectAuditMetadata = $this->getObjectAuditMetadata();
        $driver->loadMetadataForClass(AuditEntity::class, $objectAuditMetadata);

        $this->assertTrue(is_array($objectAuditMetadata->ignoredProperties));
        $this->assertEmpty($objectAuditMetadata->ignoredProperties);
    }

    public function testNotAuditEntityExtendsAuditParent()
    {
        $driver = $this->getDriver(array(
            AuditParent::class,
            NotAuditEntityExtendsAuditParent::class
        ));

        $this->assertTrue($driver->isTransient(NotAuditEntityExtendsAuditParent::class));

        $objectAuditMetadata = $this->getObjectAuditMetadata();
        $driver->loadMetadataForClass(NotAuditEntityExtendsAuditParent::class, $objectAuditMetadata);

        $this->assertTrue(is_array($objectAuditMetadata->ignoredProperties));
        $this->assertEquals(
            array('ignoredParentField'),
            $objectAuditMetadata->ignoredProperties
        );
    }

    public function testAuditEntityExtendsNotAuditParent()
    {
        $driver = $this->getDriver(array(
            NotAuditParent::class,
            AuditEntityExtendsNotAuditParent::class
        ));

        $this->assertTrue($driver->isTransient(AuditEntityExtendsNotAuditParent::class));

        $objectAuditMetadata = $this->getObjectAuditMetadata();
        $driver->loadMetadataForClass(AuditEntityExtendsNotAuditParent::class, $objectAuditMetadata);

        $this->assertTrue(is_array($objectAuditMetadata->ignoredProperties));
        $this->assertEquals(
            array('ignoredField'),
            $objectAuditMetadata->ignoredProperties
        );
    }

    public function testAuditEntityExtendsAuditParent()
    {
        $driver = $this->getDriver(array(
            AuditParent::class,
            AuditEntityExtendsAuditParent::class
        ));

        $this->assertTrue($driver->isTransient(AuditEntityExtendsAuditParent::class));

        $objectAuditMetadata = $this->getObjectAuditMetadata();
        $driver->loadMetadataForClass(AuditEntityExtendsAuditParent::class, $objectAuditMetadata);

        $this->assertTrue(is_array($objectAuditMetadata->ignoredProperties));
        $this->assertEquals(
            array(
                'ignoredParentField',
                'ignoredField'
            ),
            $objectAuditMetadata->ignoredProperties
        );
    }

    private function getObjectAuditMetadata()
    {
        return $this->getMockBuilder(ObjectAuditMetadata::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @param array $classes
     * @return DriverInterface
     */
    abstract protected function getDriver(array $classes): DriverInterface;
}
