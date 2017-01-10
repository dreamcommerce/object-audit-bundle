<?php
/*
 * (c) 2011 SimpleThings GmbH
 *
 * @package DreamCommerce\Component\ObjectAudit
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

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORM\Subscriber;

use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\Common\EventSubscriber;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORMAuditConfiguration;
use DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\ORMAuditManager;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use RuntimeException;

class CreateSchemaSubscriber implements EventSubscriber
{
    /**
     * @var ORMAuditManager
     */
    private $auditObjectManager;

    /**
     * @param ORMAuditManager $auditObjectManager
     */
    public function __construct(ORMAuditManager $auditObjectManager)
    {
        $this->auditObjectManager = $auditObjectManager;
    }

    public function getSubscribedEvents()
    {
        return [
            ToolEvents::postGenerateSchemaTable,
        ];
    }

    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs)
    {
        $classMetadata = $eventArgs->getClassMetadata();
        if (!$this->isAudited($classMetadata)) {
            return;
        }

        if (!in_array($classMetadata->inheritanceType, array(ClassMetadataInfo::INHERITANCE_TYPE_NONE, ClassMetadataInfo::INHERITANCE_TYPE_JOINED, ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE))) {
            throw new RuntimeException(sprintf('Inheritance type "%s" is not yet supported', $classMetadata->inheritanceType));
        }

        /** @var EntityManagerInterface $auditObjectManager */
        $auditObjectManager = $this->auditObjectManager->getAuditObjectManager();
        $auditTableName = $this->auditObjectManager->getAuditTableNameForClass($classMetadata->name);
        $revisionClass = $this->auditObjectManager->getRevisionClass();
        $revisionClassMetadata = $auditObjectManager->getClassMetadata($revisionClass);

        /** @var ORMAuditConfiguration $configuration */
        $configuration = $this->auditObjectManager->getConfiguration();

        $schema = $eventArgs->getSchema();
        $entityTable = $eventArgs->getClassTable();
        $auditTable = $schema->createTable($auditTableName);

        $revisionType = $configuration->getRevisionTypeFieldType();
        $auditTable->addColumn($configuration->getRevisionTypeFieldName(), $revisionType);

        foreach ($entityTable->getColumns() as $column) {
            /* @var Column $column */
            $auditTable->addColumn($column->getName(), $column->getType()->getName(), array_merge(
                $column->toArray(),
                [
                    'notnull' => false,
                    'autoincrement' => false,
                ]
            ));
        }

        $pkColumns = $entityTable->getPrimaryKey()->getColumns();
        $revPkColumns = [];

        foreach ($revisionClassMetadata->identifier as $revisionIdentifier) {
            $columnName = $revisionClassMetadata->fieldMappings[$revisionIdentifier]['columnName'];
            $columnName = $configuration->getRevisionIdFieldPrefix().$columnName.$configuration->getRevisionIdFieldSuffix();
            $type = $revisionClassMetadata->fieldMappings[$revisionIdentifier]['type'];
            $auditTable->addColumn($columnName, $type);
            $revPkColumns[] = $columnName;
        }

        $pkColumns = array_merge($pkColumns, $revPkColumns);
        $auditTable->setPrimaryKey($pkColumns);

        $revIndexName = 'rev_pk_'.md5($auditTable->getName()).'_idx';
        $auditTable->addIndex($revPkColumns, $revIndexName);
    }

    private function isAudited(ClassMetadata $classMetadata): bool
    {
        $className = $classMetadata->name;

        if (in_array(RevisionInterface::class, class_implements($className))) {
            return false;
        }

        $configuration = $this->auditObjectManager->getConfiguration();

        if (!$configuration->isClassAudited($className)) {
            $audited = false;
            if ($classMetadata->isInheritanceTypeJoined() && $classMetadata->rootEntityName == $classMetadata->name) {
                foreach ($classMetadata->subClasses as $subClass) {
                    if (in_array(RevisionInterface::class, class_implements($subClass))) {
                        continue;
                    }

                    if ($configuration->isClassAudited($subClass)) {
                        $audited = true;
                    }
                }
            }
            if (!$audited) {
                return false;
            }
        }

        return true;
    }
}
