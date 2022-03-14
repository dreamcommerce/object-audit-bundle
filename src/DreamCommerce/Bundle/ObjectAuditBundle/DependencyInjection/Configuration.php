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

namespace DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection;

use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Configuration\BaseConfiguration;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Configuration\ORMConfiguration;
use DreamCommerce\Component\ObjectAudit\Manager\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Model\Revision;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('dream_commerce_object_audit');

        $supportedDrivers = array(
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM
        );

        $rootNode
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('manager')
            ->children()
                ->scalarNode('default_manager')->end()
                ->scalarNode('driver')
                    ->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)
                    ->validate()
                        ->ifNotInArray($supportedDrivers)
                        ->thenInvalid('The driver %s is not supported. Please choose one of '.json_encode($supportedDrivers))
                    ->end()
                ->end()
            ->end()
        ;

        $this->addConfigurationSection($rootNode);
        $this->addResourcesSection($rootNode);
        $this->addManagersSection($rootNode);

        return $treeBuilder;
    }

    private function addConfigurationSection(ArrayNodeDefinition $node)
    {
        $baseConfiguration = new BaseConfiguration();
        $baseNode = new ArrayNodeDefinition('base');
        $baseConfiguration->injectPartialNode($baseNode);

        $ormConfiguration = new ORMConfiguration();
        $ormNode = new ArrayNodeDefinition('orm');
        $ormConfiguration->injectPartialNode($ormNode);

        $node
            ->children()
                ->arrayNode('configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append($baseNode)
                        ->append($ormNode)
                    ->end()
                ->end()
            ->end();
    }

    private function addManagersSection(ArrayNodeDefinition $node)
    {
        $supportedDrivers = array(
            ObjectAuditManagerInterface::DRIVER_ORM
        );

        $node
            ->children()
                ->arrayNode('managers')
                    ->useAttributeAsKey('name')
                    ->requiresAtLeastOneElement()
		    ->prototype('array')
                        ->children()
                            ->scalarNode('driver')
                                ->defaultValue(ObjectAuditManagerInterface::DRIVER_ORM)
                                ->validate()
                                    ->ifNotInArray($supportedDrivers)
                                    ->thenInvalid('The driver %s is not supported. Please choose one of '.json_encode($supportedDrivers))
                                ->end()
                            ->end()
                            ->scalarNode('object_manager')->defaultValue('default')->end()
                            ->scalarNode('audit_object_manager')->end()
                            ->arrayNode('options')
                                ->useAttributeAsKey('key')
                                ->prototype('variable')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param ArrayNodeDefinition $node
     */
    private function addResourcesSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('revision')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Revision::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(RevisionInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
