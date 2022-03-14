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

namespace DreamCommerce\Bundle\ObjectAuditBundle\Command;

use DreamCommerce\Bundle\CommonBundle\Command\BaseCommand;
use DreamCommerce\Component\ObjectAudit\Manager\ResourceAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\Manager\RevisionManagerInterface;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class ResourceDiffCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dream_commerce:audit:resource:diff')
            ->setDescription('Compares a resource at 2 different revisions')
            ->addArgument(
                'resource_name',
                InputArgument::REQUIRED,
                'Resource name'
            )
            ->addArgument(
                'resource_id',
                InputArgument::REQUIRED,
                'Resource identifier'
            )
            ->addArgument(
                'old_revision_id',
                InputArgument::OPTIONAL,
                'Old revision ID'
            )
            ->addArgument(
                'new_revision_id',
                InputArgument::OPTIONAL,
                'New revision ID'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $resourceName = $input->getArgument('resource_name');
        $resourceId = (int) $input->getArgument('resource_id');
        $oldRevisionId = (int) $input->getArgument('old_revision_id');
        $newRevisionId = (int) $input->getArgument('new_revision_id');

        $container = $this->getContainer();
        /** @var RevisionManagerInterface $revisionManager */
        $revisionManager = $container->get('dream_commerce_object_audit.revision_manager');
        /** @var ResourceAuditManagerInterface $resourceAuditManager */
        $resourceAuditManager = $container->get('dream_commerce_object_audit.resource_manager');
        $revisionRepository = $revisionManager->getRepository();

        /** @var RevisionInterface $oldRevision */
        $oldRevision = null;
        if (empty($oldRevisionId)) {
            $oldRevision = $resourceAuditManager->getInitRevision($resourceName, $resourceId);
        } else {
            $oldRevision = $revisionRepository->find($oldRevisionId);
            if ($oldRevision === null) {
                return $this->printMessageBox($output, 'The revision identified by ID #'.$oldRevisionId.' does not exist');
            }
        }

        /** @var RevisionInterface $newRevision */
        $newRevision = null;
        if (empty($newRevisionId)) {
            $newRevision = $resourceAuditManager->getRevision($resourceName, $resourceId);
        } else {
            $newRevision = $revisionRepository->find($newRevisionId);
            if ($newRevision === null) {
                return $this->printMessageBox($output, 'The revision identified by ID #'.$newRevisionId.' does not exist');
            }
        }

        if ($oldRevision->getId() == $newRevision->getId()) {
            return $this->printMessageBox($output, 'Nothing to compare, same revisions');
        }

        if ($oldRevision->getId() > $newRevision->getId()) {
            $tmpRevision = $oldRevision;
            $oldRevision = $newRevision;
            $newRevision = $tmpRevision;
        }

        $this->printMessageBox($output, 'Difference between revision ID #'.$oldRevision->getId().' and ID #'.$newRevision->getId().' for resource '.$resourceName.' with given ID #'.$resourceId, 'info');

        $cloner = new VarCloner();
        $dumper = new CliDumper();

        $dumper->dump($cloner->cloneVar($oldRevision));
        $dumper->dump($cloner->cloneVar($newRevision));

        $diffRows = $resourceAuditManager->diffRevisions($resourceName, $resourceId, $oldRevision, $newRevision);

        if (empty($diffRows)) {
            return $this->printMessageBox($output, 'Nothing to compare, same resource data');
        }

        $rows = array();
        foreach ($diffRows as $fieldName => $diffRow) {
            foreach (array('old', 'same', 'new') as $type) {
                if(is_object($diffRow[$type])) {
                    if ($diffRow[$type] instanceof \DateTime) {
                        $diffRow[$type] = $diffRow[$type]->format(\DateTime::ISO8601);
                    } else {
                        $diffRow[$type] = get_class($diffRow[$type]);
                    }
                } elseif ($diffRow[$type] === null) {
                    $diffRow[$type] = '--';
                }

                if(is_array($diffRow[$type])) {
                    $diffRow[$type] = var_export($diffRow[$type], true);
                }
            }

            $rows[] = array(
                $fieldName,
                $diffRow['old'],
                $diffRow['same'],
                $diffRow['new'],
            );
        }

        $table = new Table($output);
        $table
            ->setHeaders(array('Field', 'Deleted', 'Same', 'Updated'))
            ->setRows($rows);
        $table->render();

        $output->writeln('');
    }
}
