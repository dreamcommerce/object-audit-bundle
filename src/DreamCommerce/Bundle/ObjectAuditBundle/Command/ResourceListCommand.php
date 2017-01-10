<?php

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
        $table->setHeaders(['Resource', 'Class']);

        foreach ($resources as $resource) {
            $metadata = $resourceRegistry->get($resource);
            $table->addRow([
                $resource,
                $metadata->getClass('model'),
            ]);
        }

        $table->render();
    }
}
