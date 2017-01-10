<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends ContainerAwareCommand
{
    /**
     * @param OutputInterface $output
     * @param string          $message
     * @param string          $type
     */
    protected function printMessageBox(OutputInterface $output, $message, $type = 'error')
    {
        $formatter = $this->getHelper('formatter');
        $messages = ['', $message, ''];

        $formattedBlock = $formatter->formatBlock($messages, $type);
        $output->writeln($formattedBlock);
    }
}
