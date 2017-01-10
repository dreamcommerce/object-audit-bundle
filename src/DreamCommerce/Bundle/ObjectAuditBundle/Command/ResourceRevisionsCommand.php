<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Command;

use DreamCommerce\Component\ObjectAudit\ResourceAuditManagerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResourceRevisionsCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dream_commerce:audit:resource:revisions')
            ->setDescription('Lists revisions for the supplied resource')
            ->addArgument(
                'resource_name',
                InputArgument::REQUIRED,
                'Resource name'
            )
            ->addArgument(
                'resource_id',
                InputArgument::REQUIRED,
                'Resource identifier'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $resourceName = $input->getArgument('resource_name');
        $resourceId = $input->getArgument('resource_id');

        /** @var ResourceAuditManagerInterface $resourceAuditManager */
        $resourceAuditManager = $this->getContainer()->get('dream_commerce_object_audit.resource_manager');

        $output->writeln('');

        $revisions = $resourceAuditManager->findResourceRevisions($resourceName, $resourceId);
        $rows = [];

        foreach ($revisions as $revision) {
            $rows[] = [$revision->getId(), $revision->getCreatedAt()->format(\DateTime::ISO8601)];
        }

        $table = new Table($output);
        $table
            ->setHeaders(array('ID', 'Created At'))
            ->setRows($rows)
        ;
        $table->render();

        $output->writeln('');
    }
}
