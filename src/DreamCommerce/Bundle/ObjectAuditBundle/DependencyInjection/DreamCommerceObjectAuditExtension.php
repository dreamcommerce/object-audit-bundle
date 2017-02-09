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

namespace DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationRegistry;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Compiler\ManagerCompilerPass;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Configuration\ORMConfiguration;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use RuntimeException;

final class DreamCommerceObjectAuditExtension extends AbstractResourceExtension
{
    const ALIAS = 'dream_commerce_object_audit';

    /**
     * {@inheritdoc}
     */
    public function load(array $config, ContainerBuilder $container)
    {
        // use composer autoloader
        AnnotationRegistry::registerLoader('class_exists');

        $config = $this->processConfiguration($this->getConfiguration($config, $container), $config);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load(sprintf('services/integrations/%s.xml', $config['driver']));

        $this->registerResources('dream_commerce', $config['driver'], $config['resources'], $container);
        $this->mapFormValidationGroupsParameters($config, $container);
        $loader->load('services.xml');

        $partialConfiguration = null;
        $configPartName = null;

        switch ($config['driver']) {
            case SyliusResourceBundle::DRIVER_DOCTRINE_ORM:
                $partialConfiguration = new ORMConfiguration();
                $configPartName = 'orm';
                break;
            default:
                throw new RuntimeException('Unsupported type of driver "'.$config['driver'].'"');
        }

        foreach ($config['managers'] as $name => $managerConfig) {
            if (isset($config['configuration'][$configPartName])) {
                $managerConfig = array_merge($config['configuration'][$configPartName], $managerConfig);
            }
            $managerConfig = $this->processConfiguration($partialConfiguration, array($managerConfig));
            $config['managers'][$name] = $managerConfig;
        }

        $container->setParameter($this->getAlias().'.managers', $config['managers']);
        $container->addCompilerPass(new ManagerCompilerPass($config['driver']));
    }

    public function getAlias()
    {
        return self::ALIAS;
    }
}
