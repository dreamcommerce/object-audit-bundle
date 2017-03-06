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

namespace DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Configuration;

use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionUInt16Type;
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
                ->scalarNode('table_prefix')->defaultValue('')->end()
                ->scalarNode('table_suffix')->defaultValue('_audit')->end()
                ->scalarNode('revision_id_field_prefix')->defaultValue('revision_')->end()
                ->scalarNode('revision_id_field_suffix')->defaultValue('')->end()
                ->scalarNode('revision_type_field_name')->defaultValue('revision_type')->end()
                ->scalarNode('revision_type_field_type')->defaultValue(RevisionUInt16Type::TYPE_NAME)->end()
            ->end();
    }
}
