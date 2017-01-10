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
            $rows = [];

            foreach ($changedResources as $changedResource) {
                $object = $changedResource->getObject();
                $rows[] = [$object->getId(), get_class($object), $changedResource->getRevisionType()];
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
