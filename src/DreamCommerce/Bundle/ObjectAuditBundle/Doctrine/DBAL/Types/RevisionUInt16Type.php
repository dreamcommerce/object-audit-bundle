<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types;

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

final class RevisionUInt16Type extends MapEnumType
{
    const TYPE_NAME = 'enumRevisionUInt16Type';

    /**
     * @var string
     */
    protected $enumType = self::TYPE_UINT16;

    /**
     * @var string
     */
    protected $name = self::TYPE_NAME;

    /**
     * @var array
     */
    protected $values = array(
        RevisionInterface::ACTION_INSERT => 1,
        RevisionInterface::ACTION_UPDATE => 2,
        RevisionInterface::ACTION_DELETE => 3,
    );
}
