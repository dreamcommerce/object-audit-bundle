<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\Compiler;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Persistence\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\EntityManagerInterface;
use DreamCommerce\Bundle\ObjectAuditBundle\DependencyInjection\DreamCommerceObjectAuditExtension;
use DreamCommerce\Component\ObjectAudit\Metadata\Driver\AnnotationDriver as AuditAnnotationDriver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use RuntimeException;

final class ManagerCompilerPass implements CompilerPassInterface
{
    /**
     * @var string
     */
    private $driver;

    public function __construct(string $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->findDefinition(
            DreamCommerceObjectAuditExtension::ALIAS.'.registry'
        );

        $annotationReader = new AnnotationReader();

        foreach ($container->getParameter(DreamCommerceObjectAuditExtension::ALIAS.'.managers') as $name => $manager) {
            $id = DreamCommerceObjectAuditExtension::ALIAS.'.manager.'.$name;

            $configurationClass = $container->getParameter('dream_commerce_object_audit.configuration.class');
            $configuration = new Definition($configurationClass);

            /** @var EntityManagerInterface $persistManager */
            $persistManager = $container->get($manager['persist_manager']);
            $driver = $persistManager->getConfiguration()->getMetadataDriverImpl();
            $auditDriver = null;
            if ($driver instanceof AnnotationDriver) {
                $auditDriver = new AuditAnnotationDriver($annotationReader);
            } else {
                throw new RuntimeException('Unsupported type of driver "'.get_class($driver).'"');
            }

            $metadataFactoryClass = $container->getParameter('dream_commerce_object_audit.metadata_factory.class');
            $metadataFactory = new Definition($metadataFactoryClass);
            $metadataFactory->setArguments(array(
                $persistManager,
                $auditDriver,
            ));

            $managerClass = $container->getParameter('dream_commerce_object_audit.manager.class');
            $managerDefinition = new Definition($managerClass);
            $managerDefinition->setArguments(array(
                $configuration,
                $persistManager,
                $container->getDefinition('dream_commerce_object_audit.revision_manager'),
                $container->getDefinition('dream_commerce_object_audit.factory'),
                $metadataFactory,
            ));

            $container->setDefinition($id, $managerDefinition);

            $definition->addMethodCall(
                'registerObjectAuditManager',
                array(
                    $name,
                    $persistManager,
                    new Reference($id),
                )
            );
        }
    }
}
