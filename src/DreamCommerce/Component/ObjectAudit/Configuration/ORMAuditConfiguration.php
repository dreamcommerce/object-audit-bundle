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

namespace DreamCommerce\Component\ObjectAudit\Configuration;

use DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types\RevisionUInt8Type;

class ORMAuditConfiguration extends BaseAuditConfiguration
{
    /**
     * @var string
     */
    private $tablePrefix = '';

    /**
     * @var string
     */
    private $tableSuffix = '_audit';

    /**
     * @var string
     */
    private $revisionIdFieldPrefix = 'revision_';

    /**
     * @var string
     */
    private $revisionIdFieldSuffix = '';

    /**
     * @var string
     */
    private $revisionTypeFieldName = 'revision_type';

    /**
     * @var string
     */
    private $revisionTypeFieldType = RevisionUInt8Type::TYPE_NAME;

    /**
     * @return string
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * @param string $prefix
     *
     * @return $this
     */
    public function setTablePrefix(string $prefix)
    {
        $this->tablePrefix = $prefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getTableSuffix(): string
    {
        return $this->tableSuffix;
    }

    /**
     * @param string $suffix
     *
     * @return $this
     */
    public function setTableSuffix(string $suffix)
    {
        $this->tableSuffix = $suffix;

        return $this;
    }

    /**
     * @return string
     */
    public function getRevisionIdFieldPrefix(): string
    {
        return $this->revisionIdFieldPrefix;
    }

    /**
     * @param string $revisionIdFieldPrefix
     *
     * @return $this
     */
    public function setRevisionIdFieldPrefix(string $revisionIdFieldPrefix)
    {
        $this->revisionIdFieldPrefix = $revisionIdFieldPrefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getRevisionIdFieldSuffix(): string
    {
        return $this->revisionIdFieldSuffix;
    }

    /**
     * @param string $revisionIdFieldSuffix
     *
     * @return $this
     */
    public function setRevisionIdFieldSuffix(string $revisionIdFieldSuffix)
    {
        $this->revisionIdFieldSuffix = $revisionIdFieldSuffix;

        return $this;
    }

    /**
     * @return string
     */
    public function getRevisionTypeFieldName(): string
    {
        return $this->revisionTypeFieldName;
    }

    /**
     * @param string $revisionTypeFieldName
     *
     * @return $this
     */
    public function setRevisionTypeFieldName(string $revisionTypeFieldName)
    {
        $this->revisionTypeFieldName = $revisionTypeFieldName;

        return $this;
    }

    /**
     * @return string
     */
    public function getRevisionTypeFieldType(): string
    {
        return $this->revisionTypeFieldType;
    }

    /**
     * @param string $revisionTypeFieldType
     *
     * @return $this
     */
    public function setRevisionTypeFieldType(string $revisionTypeFieldType)
    {
        $this->revisionTypeFieldType = $revisionTypeFieldType;

        return $this;
    }
}
