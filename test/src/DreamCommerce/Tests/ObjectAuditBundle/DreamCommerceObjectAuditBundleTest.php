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
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\DreamCommerceObjectAuditExtension;
use DreamCommerce\Component\Common\Factory\DateTimeFactory;
use DreamCommerce\Component\ObjectAudit\Manager\RevisionManagerInterface;
use DreamCommerce\Component\ObjectAudit\Model\Revision;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DreamCommerceObjectAuditBundleTest extends WebTestCase
{
    public function testInitServices()
    {
        /** @var ContainerInterface $container */
        $container = self::createClient()->getContainer();
        $services = array(
            DreamCommerceObjectAuditExtension::ALIAS . '.orm.factory',
            DreamCommerceObjectAuditExtension::ALIAS . '.registry',
            DreamCommerceObjectAuditExtension::ALIAS . '.resource_manager',
            DreamCommerceObjectAuditExtension::ALIAS . '.resource_metadata_factory',
            DreamCommerceObjectAuditExtension::ALIAS . '.revision_manager'
        );

        foreach ($services as $id) {
            $container->get($id);
        }
    }

    public function testJmsSerializer()
    {
        /** @var ContainerInterface $container */
        $container = self::createClient()->getContainer();

        $this->assertTrue($container->has('jms_serializer'));
        /** @var SerializerInterface $serializer */
        $serializer = $container->get('jms_serializer');

        $dateStr = '2015-08-07T23:59:57+0000';
        $dateTime = new DateTime($dateStr);
        /** @var DateTimeFactory $dateFactory */
        $dateFactory = $this->createMock(DateTimeFactory::class);
        $dateFactory
            ->method('createNew')
            ->willReturn($dateTime);

        $revision = new Revision($dateFactory);

        $actual = $serializer->serialize($revision, 'json');
        $expected = json_encode(
            array(
                'created_at' => $dateStr
            )
        );

        $this->assertEquals($expected, $actual);


        /** @var RevisionManagerInterface $revisionManager */
        $revisionManager = $container->get(DreamCommerceObjectAuditExtension::ALIAS . '.revision_manager');
        $persistManager = $revisionManager->getPersistManager();
        $persistManager->persist($revision);
        $persistManager->flush();

        $actual = $serializer->serialize($revision, 'json');

        $expected = json_encode(
            array(
                'id' => $revision->getId(),
                'created_at' => $dateStr
            )
        );

        $this->assertEquals($expected, $actual);
    }
}
