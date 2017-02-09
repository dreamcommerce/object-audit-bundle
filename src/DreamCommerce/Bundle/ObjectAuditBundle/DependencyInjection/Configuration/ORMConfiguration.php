<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Configuration;

use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionUInt8Type;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class ORMConfiguration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('orm');

        $baseConfiguration = new BaseConfiguration();
        $baseConfiguration->injectPartialNode($rootNode);

        $this->injectPartialNode($rootNode);

        return $treeBuilder;
    }
    public function injectPartialNode(ArrayNodeDefinition $node)
    {
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('object_manager')->defaultValue('default')->end()
                ->scalarNode('table_prefix')->defaultValue('')->end()
                ->scalarNode('table_suffix')->defaultValue('_audit')->end()
                ->scalarNode('revision_id_field_prefix')->defaultValue('revision_')->end()
                ->scalarNode('revision_id_field_suffix')->defaultValue('')->end()
                ->scalarNode('revision_type_field_name')->defaultValue('revision_type')->end()
                ->scalarNode('revision_type_field_type')->defaultValue(RevisionUInt8Type::TYPE_NAME)->end()
            ->end();
    }
}
