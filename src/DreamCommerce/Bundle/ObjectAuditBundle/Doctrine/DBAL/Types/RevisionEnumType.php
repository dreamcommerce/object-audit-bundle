<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types;

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

final class RevisionEnumType extends EnumType
{
    const TYPE_NAME = 'enumRevisionType';

    /**
     * @var string
     */
    protected $name = self::TYPE_NAME;

    /**
     * @var array
     */
    protected $values = array(
        RevisionInterface::ACTION_INSERT,
        RevisionInterface::ACTION_UPDATE,
        RevisionInterface::ACTION_DELETE,
    );
}
