<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Webmozart\Assert\Assert;

abstract class EnumType extends Type
{
    protected $name;
    protected $values = array();
    protected $flipValues;

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $values = array_map(function ($val) {
            return "'".$val."'";
        }, $this->values);

        return 'ENUM('.implode(', ', $values).") COMMENT '(DC2Type:".$this->getName().")'";
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }
    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        Assert::oneOf($value, $this->values);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }
}
