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

namespace DreamCommerce\Component\ObjectAudit\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

class ORMRevisionManager extends BaseRevisionManager
{
    /**
     * {@inheritdoc}
     */
    public function save(ObjectManager $persistManager)
    {
        if ($this->revision !== null) {
            /** @var EntityManagerInterface $auditPersistManager */
            $auditPersistManager = $this->persistManager;
            $uow = $auditPersistManager->getUnitOfWork();
            /** @var ClassMetadata $revisionMetadata */
            $revisionMetadata = $this->getMetadata();

            $uow->persist($this->revision);
            $uow->computeChangeSet($revisionMetadata, $this->revision);

            if ($auditPersistManager != $persistManager) {
                $auditPersistManager->flush();
            }
        }
    }
}
