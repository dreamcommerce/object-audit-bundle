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

namespace DreamCommerce\Bundle\ObjectAuditBundle\Command;

use DreamCommerce\Component\ObjectAudit\ResourceAuditConfiguration;
use DreamCommerce\Component\ObjectAudit\ResourceAuditManagerInterface;
use Sylius\Component\Resource\Metadata\RegistryInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResourceListCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dream_commerce:audit:resource:list')
            ->setDescription('Lists audited resources');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ResourceAuditManagerInterface $auditManager */
        $resourceAuditManager = $this->getContainer()->get('dream_commerce_object_audit.resource_manager');

        /** @var RegistryInterface $resourceRegistry */
        $resourceRegistry = $this->getContainer()->get('sylius.resource_registry');
        /** @var ResourceAuditConfiguration $configuration */
        $configuration = $resourceAuditManager->getConfiguration();
        $resources = $configuration->getAuditedResources();
        ksort($resources);

        $table = new Table($output);
        $table->setHeaders(array('Resource', 'Class'));

        foreach ($resources as $resource) {
            $metadata = $resourceRegistry->get($resource);
            $table->addRow(array(
                $resource,
                $metadata->getClass('model'),
            ));
        }

        $table->render();
    }
}
