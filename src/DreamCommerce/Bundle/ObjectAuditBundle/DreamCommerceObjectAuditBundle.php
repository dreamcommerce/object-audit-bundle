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

declare(strict_types=1);

namespace DreamCommerce\Bundle\ObjectAuditBundle;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Compiler\ManagerCompilerPass;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionEnumType;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionIntegerType;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionSmallIntType;
use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionTinyIntType;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DreamCommerceObjectAuditBundle extends AbstractResourceBundle
{
    public function boot()
    {
        parent::boot();

        /** @var Registry $registry */
        $registry = $this->container->get('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        if ($registry !== null) {
            $defaultType = 'dc_revision_action';

            /** @var Connection $connection */
            foreach ($registry->getConnections() as $connection) {
                $platform = $connection->getDatabasePlatform();

                $types = array(
                    RevisionEnumType::TYPE_NAME => RevisionEnumType::class,
                    RevisionTinyIntType::TYPE_NAME => RevisionTinyIntType::class,
                    RevisionSmallIntType::TYPE_NAME => RevisionSmallIntType::class,
                    RevisionIntegerType::TYPE_UINT32 => RevisionIntegerType::class,
                );

                foreach ($types as $type => $className) {
                    if (!Type::hasType($type)) {
                        Type::addType($type, $className);
                        $platform->registerDoctrineTypeMapping($type, $type);
                    }
                }

                if (!Type::hasType($defaultType)) {
                    Type::addType($defaultType, RevisionSmallIntType::class);
                    $platform->registerDoctrineTypeMapping($defaultType, $defaultType);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ManagerCompilerPass(), PassConfig::TYPE_OPTIMIZE, -100);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedDrivers(): array
    {
        return array(
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getModelNamespace(): ?string
    {
        return 'DreamCommerce\Component\ObjectAudit\Model';
    }
}
