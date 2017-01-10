<?php

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

abstract class MapEnumType extends EnumType
{
    const TYPE_UINT8 = 'UINT8';
    const TYPE_UINT16 = 'UINT16';

    protected $enumType;

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        $definition = null;

        switch ($this->enumType) {
            case static::TYPE_UINT8:
                $platformName = $platform->getName();
                Assert::oneOf($platformName, array('mysql'));
                $definition = 'TINYINT(1)';

                break;

            case static::TYPE_UINT16:
                $definition = $platform->getSmallIntTypeDeclarationSQL($fieldDeclaration);
                break;

            default:
                throw new InvalidArgumentException('Unsupported enum type "'.$this->enumType.'"');
        }

        switch ($this->enumType) {
            case static::TYPE_UINT8:
            case static::TYPE_UINT16:
                $definition .= ' unsigned';
                break;
        }

        $definition .= ' COMMENT "(DC2Type:'.$this->getName().')"';

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($this->flipValues == null) {
            $this->flipValues = array_flip($this->values);
        }

        Assert::keyExists($this->flipValues, $value);

        return $this->flipValues[$value];
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        Assert::keyExists($this->values, $value);

        return $this->values[$value];
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingType()
    {
        switch ($this->enumType) {
            case static::TYPE_UINT8:
            case static::TYPE_UINT16:
                return \PDO::PARAM_INT;
        }

        return \PDO::PARAM_STR;
    }
}
