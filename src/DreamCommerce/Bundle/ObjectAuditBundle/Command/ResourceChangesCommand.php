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

use DreamCommerce\Component\ObjectAudit\Manager\ResourceAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Manager\RevisionManagerInterface;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class ResourceChangesCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dream_commerce:audit:resource:changes')
            ->setDescription('Shows resources changed in the specified revision')
            ->addArgument(
                'revision_id',
                InputArgument::REQUIRED,
                'Revision identifier'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $revisionId = $input->getArgument('revision_id');

        $container = $this->getContainer();
        /** @var RevisionManagerInterface $revisionManager */
        $revisionManager = $container->get('dream_commerce_object_audit.revision_manager');
        /** @var ResourceAuditManagerInterface $resourceAuditManager */
        $resourceAuditManager = $container->get('dream_commerce_object_audit.resource_manager');

        $revisionRepository = $revisionManager->getRevisionRepository();
        /** @var RevisionInterface $revision */
        $revision = $revisionRepository->find($revisionId);

        if ($revision === null) {
            return $this->printMessageBox($output, 'The revision identified by ID #'.$revisionId.' does not exist');
        } else {
            $cloner = new VarCloner();
            $dumper = new CliDumper();

            $dumper->dump($cloner->cloneVar($revision));

            $auditResources = $resourceAuditManager->findAllResourcesChangedAtRevision($revision);
            $rows = array();

            foreach ($auditResources as $auditResource) {
                $object = $auditResource->getObject();
                $rows[] = array($object->getId(), get_class($object), $auditResource->getRevisionType());
            }

            $table = new Table($output);
            $table
                ->setHeaders(array('ID', 'Resource class name', 'Revision Type'))
                ->setRows($rows)
            ;
            $table->render();
        }

        $output->writeln('');
    }
}
