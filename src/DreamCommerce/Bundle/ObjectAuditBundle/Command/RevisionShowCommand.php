<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Command;

use DreamCommerce\Component\ObjectAudit\Repository\RevisionRepositoryInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class RevisionShowCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dream_commerce:audit:revision:show')
            ->setDescription('Show information about the revision')
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
        $revisionId = $input->getArgument('revision_id');

        /** @var RevisionRepositoryInterface $revisionRepository */
        $revisionRepository = $this->getContainer()->get('dream_commerce.repository.revision');

        if (empty($revisionId)) {
            $revision = $revisionRepository->findCurrentRevision();
        } else {
            $revision = $revisionRepository->find($revisionId);
        }

        if ($revision === null) {
            if (empty($revisionId)) {
                $message = 'There is no revision';
            } else {
                $message = 'The revision identified by ID #'.$revisionId.' does not exist';
            }
            $this->printMessageBox($output, $message);
        } else {
            $message = 'The revision ID #'.$revision->getId().' was created at '.$revision->getCreatedAt()->format('Y-m-d H:i:s');
            $this->printMessageBox($output, $message, 'info');

            $cloner = new VarCloner();
            $dumper = new CliDumper();

            $dumper->dump($cloner->cloneVar($revision));
        }
        $output->writeln('');
    }
}
