<?php

/*
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

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ResourceAuditManagerInterface;
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

        /** @var ResourceAuditManagerInterface $resourceAuditManager */
        $resourceAuditManager = $this->getContainer()->get('dream_commerce_object_audit.resource_manager');
        $objectAuditManager = $resourceAuditManager->getObjectAuditManager();

        $revisionRepository = $objectAuditManager->getRevisionRepository();
        /** @var RevisionInterface $revision */
        $revision = $revisionRepository->find($revisionId);

        if ($revision === null) {
            return $this->printMessageBox($output, 'The revision identified by ID #'.$revisionId.' does not exist');
        } else {
            $cloner = new VarCloner();
            $dumper = new CliDumper();

            $dumper->dump($cloner->cloneVar($revision));

            $changedResources = $resourceAuditManager->findAllResourcesChangedAtRevision($revision);
            $rows = array();

            foreach ($changedResources as $changedResource) {
                $object = $changedResource->getObject();
                $rows[] = array($object->getId(), get_class($object), $changedResource->getRevisionType());
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
