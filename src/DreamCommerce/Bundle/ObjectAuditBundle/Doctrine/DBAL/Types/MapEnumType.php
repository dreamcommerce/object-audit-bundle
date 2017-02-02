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

namespace DreamCommerce\Bundle\ObjectAuditBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

abstract class MapEnumType extends EnumType
{
    const TYPE_UINT8 = 'UINT8';
    const TYPE_UINT16 = 'UINT16';
    const TYPE_UINT32 = 'UINT32';

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

            case static::TYPE_UINT32:
                $definition = $platform->getIntegerTypeDeclarationSQL($fieldDeclaration);
                break;

            default:
                throw new InvalidArgumentException('Unsupported enum type "'.$this->enumType.'"');
        }

        switch ($this->enumType) {
            case static::TYPE_UINT8:
            case static::TYPE_UINT16:
            case static::TYPE_UINT32:
                $definition .= ' unsigned';
                break;
        }

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
            case static::TYPE_UINT32:
                return \PDO::PARAM_INT;
        }

        return \PDO::PARAM_STR;
    }
}
