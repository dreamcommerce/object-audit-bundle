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

namespace DreamCommerce\Bundle\ObjectAuditBundle;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionEnumType;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionUInt16Type;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionUInt32Type;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionUInt8Type;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\UTCDateTimeType;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;

class DreamCommerceObjectAuditBundle extends AbstractResourceBundle
{
    public function boot()
    {
        parent::boot();

        /** @var Registry $registry */
        $registry = $this->container->get('doctrine');
        /** @var Connection $connection */
        foreach ($registry->getConnections() as $connection) {
            $platform = $connection->getDatabasePlatform();

            $types = array(
                RevisionEnumType::TYPE_NAME => RevisionEnumType::class,
                RevisionUInt8Type::TYPE_NAME => RevisionUInt8Type::class,
                RevisionUInt16Type::TYPE_NAME => RevisionUInt16Type::class,
                RevisionUInt32Type::TYPE_UINT32 => RevisionUInt32Type::class,
                UTCDateTimeType::TYPE_NAME => UTCDateTimeType::class,
            );

            foreach ($types as $type => $className) {
                if (!Type::hasType($type)) {
                    Type::addType($type, $className);
                    $platform->registerDoctrineTypeMapping($type, $type);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedDrivers()
    {
        return array(
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getModelNamespace()
    {
        return 'DreamCommerce\Component\ObjectAudit\Model';
    }
}
