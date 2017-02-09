<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Configuration;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class BaseConfiguration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('base');
        $this->injectPartialNode($rootNode);

        return $treeBuilder;
    }
    public function injectPartialNode(ArrayNodeDefinition $node)
    {
        $node
            ->fixXmlConfig('ignore_properties')
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('ignore_properties')
                    ->treatNullLike(array())
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('options')
                    ->useAttributeAsKey('key')
                    ->prototype('variable')->end()
                ->end()
            ->end();
    }
}
