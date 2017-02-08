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

namespace DreamCommerce\Component\ObjectAudit\Doctrine\DBAL\Types;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;
use Webmozart\Assert\Assert;

class UTCDateTimeType extends DateTimeType
{
    const TYPE_NAME = 'utcDateTimeType';

    private static $utc = null;

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        /* @var DateTime $value */
        Assert::isInstanceOf($value, DateTime::class);

        if (is_null(self::$utc)) {
            self::$utc = new DateTimeZone('UTC');
        }

        $value->setTimeZone(self::$utc);

        return $value->format($platform->getDateTimeFormatString());
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        if (is_null(self::$utc)) {
            self::$utc = new DateTimeZone('UTC');
        }

        $val = DateTime::createFromFormat($platform->getDateTimeFormatString(), $value, self::$utc);

        if (!$val) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }

        return $val;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}
