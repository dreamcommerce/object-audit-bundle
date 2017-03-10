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
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Configuration\ORMConfiguration;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use RuntimeException;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Webmozart\Assert\Assert;

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

        $globalBaseOptions = array();
        if (isset($config['configuration']['base'])) {
            $globalBaseOptions = $config['configuration']['base'];
        }

        foreach ($config['managers'] as $name => $managerConfig) {
            if(empty($managerConfig['audit_object_manager'])) {
                $config['managers'][$name]['audit_object_manager'] = $managerConfig['object_manager'];
            }

            $globalPartialOptions = array();
            if (isset($config['configuration'][$managerConfig['driver']])) {
                $globalPartialOptions = $config['configuration'][$managerConfig['driver']];
            }

            $partialConfiguration = null;

            switch ($managerConfig['driver']) {
                case ObjectAuditManagerInterface::DRIVER_ORM:
                    $partialConfiguration = new ORMConfiguration();
                    break;
                default:
                    throw new RuntimeException('Unsupported type of driver "'.$managerConfig['driver'].'"');
            }

            $partialOptions = $partialOptions = array_merge($globalBaseOptions, $globalPartialOptions, $managerConfig['options']);
            $partialOptions = $this->processConfiguration($partialConfiguration, array($partialOptions));
            $config['managers'][$name]['options'] = $partialOptions;
        }

        $defaultManager = $config['default_manager'];
        if (count($config['managers']) > 0) {
            if (!empty($defaultManager)) {
                if (!isset($config['managers'][$defaultManager])) {
                    throw new RuntimeException('Audit manager "' . $defaultManager . '" does not exist');
                }
            } else {
                reset($config['managers']);
                $defaultManager = key($config['managers']);
            }
        }

        $container->setParameter($this->getAlias().'.default_manager', $defaultManager);
        $container->setParameter($this->getAlias().'.managers', $config['managers']);
    }

    public function getAlias()
    {
        return self::ALIAS;
    }
}
