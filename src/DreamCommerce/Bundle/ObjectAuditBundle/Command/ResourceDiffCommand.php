<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Command;

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ResourceAuditManagerInterface;
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
        $resourceId = $input->getArgument('resource_id');
        $oldRevisionId = $input->getArgument('old_revision_id');
        $newRevisionId = $input->getArgument('new_revision_id');

        /** @var ResourceAuditManagerInterface $resourceAuditManager */
        $resourceAuditManager = $this->getContainer()->get('dream_commerce_object_audit.resource_manager');
        $objectAuditManager = $resourceAuditManager->getObjectAuditManager();
        $revisionRepository = $objectAuditManager->getRevisionRepository();

        /** @var RevisionInterface $oldRevision */
        $oldRevision = null;
        if (empty($oldRevisionId)) {
            $oldRevision = $resourceAuditManager->getInitializeResourceRevision($resourceName, $resourceId);
        } else {
            $oldRevision = $revisionRepository->find($oldRevisionId);
            if ($oldRevision === null) {
                return $this->printMessageBox($output, 'The revision identified by ID #'.$oldRevisionId.' does not exist');
            }
        }

        /** @var RevisionInterface $newRevision */
        $newRevision = null;
        if (empty($newRevisionId)) {
            $newRevision = $resourceAuditManager->getCurrentResourceRevision($resourceName, $resourceId);
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

        $diffRows = $resourceAuditManager->diffResourceRevisions($resourceName, $resourceId, $oldRevision, $newRevision);

        if (empty($diffRows)) {
            return $this->printMessageBox($output, 'Nothing to compare, same resource data');
        }

        $rows = [];
        foreach ($diffRows as $fieldName => $diffRow) {
            foreach (['old', 'same', 'new'] as $type) {
                if ($diffRow[$type] instanceof \DateTime) {
                    $diffRow[$type] = $diffRow[$type]->format(\DateTime::ISO8601);
                } elseif ($diffRow[$type] === null) {
                    $diffRow[$type] = '--';
                }
            }

            $rows[] = [
                $fieldName,
                $diffRow['old'],
                $diffRow['same'],
                $diffRow['new'],
            ];
        }

        $table = new Table($output);
        $table
            ->setHeaders(array('Field', 'Deleted', 'Same', 'Updated'))
            ->setRows($rows);
        $table->render();

        $output->writeln('');
    }
}
