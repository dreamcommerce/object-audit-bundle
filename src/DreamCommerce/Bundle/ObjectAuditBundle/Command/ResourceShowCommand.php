<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Command;

use DreamCommerce\Component\ObjectAudit\ResourceAuditManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class ResourceShowCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dream_commerce:audit:resource:show')
            ->setDescription('Shows the data for a resource at the specified revision')
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
                'revision_id',
                InputArgument::OPTIONAL,
                'Revision identifier'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $resourceName = $input->getArgument('resource_name');
        $resourceId = $input->getArgument('resource_id');
        $revisionId = $input->getArgument('revision_id');

        /** @var ResourceAuditManagerInterface $resourceAuditManager */
        $resourceAuditManager = $this->getContainer()->get('dream_commerce_object_audit.resource_manager');
        $revisionRepository = $resourceAuditManager->getObjectAuditManager()->getRevisionRepository();

        if (empty($revisionId)) {
            $revision = $resourceAuditManager->getCurrentResourceRevision($resourceName, $resourceId);
        } else {
            $revision = $revisionRepository->find($revisionId);
        }

        if ($revision === null) {
            if (empty($revisionId)) {
                $message = 'There is no revision for resource '.$resourceName.' ID #'.$resourceId;
            } else {
                $message = 'Revision identified by ID #'.$revisionId.' does not exist';
            }
            $this->printMessageBox($output, $message);
        } else {
            $auditEntity = $resourceAuditManager->findResourceByRevision($resourceName, $resourceId, $revision);

            $message = 'The revision ID #'.$revision->getId().' for resource '.$resourceName.' ID #'.$resourceId.' was created at '.$revision->getCreatedAt()->format('Y-m-d H:i:s');
            $this->printMessageBox($output, $message, 'info');

            $cloner = new VarCloner();
            $dumper = new CliDumper();

            $dumper->dump($cloner->cloneVar($revision));
            $dumper->dump($cloner->cloneVar($auditEntity));
        }
        $output->writeln('');
    }
}
