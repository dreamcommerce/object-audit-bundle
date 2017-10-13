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

declare(strict_types=1);

namespace DreamCommerce\Component\ObjectAudit\Metadata\Driver;

use Doctrine\Common\Persistence\Mapping\Driver\FileLocator;

abstract class FileDriver implements DriverInterface
{
    /**
     * @var array
     */
    private $mapping = array();

    /**
     * @var FileLocator
     */
    protected $locator;

    /**
     * @param FileLocator $locator
     */
    public function setLocator(FileLocator $locator): void
    {
        $this->locator = $locator;
    }

    /**
     * Loads a mapping file with the given name and returns a map
     * from class/entity names to their corresponding elements.
     *
     * @param string $file The mapping file to load.
     *
     * @return array
     */
    abstract protected function loadMappingFile(string $file): array;

    /**
     * Tries to get a mapping for a given class
     *
     * @param string $className
     *
     * @return null|array|object
     */
    protected function _getMapping(string $className)
    {
        if (!array_key_exists($className, $this->mapping)) {
            $mapping = $this->loadMappingFile($this->locator->findMappingFile($className));
            $this->mapping[$className] = null;
            if (isset($mapping[$className])) {
                $this->mapping[$className] = $mapping[$className];
            }
        }

        return $this->mapping[$className];
    }
}
