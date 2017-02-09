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

namespace DreamCommerce\Tests\ObjectAudit;

use DateTime;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\DuplicateRevisionFailureTestOwnedElement;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\DuplicateRevisionFailureTestPrimaryOwner;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\DuplicateRevisionFailureTestSecondaryOwner;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\EscapedColumnsEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue111Entity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue156Client;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue156ContactTelephoneNumber;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue194Address;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue194User;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue196Entity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue198Car;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue198Owner;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue31Reve;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue31User;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue87Organization;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue87Project;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue87ProjectComment;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue9Address;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Issue\Issue9Customer;
use DreamCommerce\Tests\ObjectAudit\Types\Issue196Type;

class IssueTest extends BaseTest
{
    protected $fixturesPath = __DIR__ . '/Fixtures/Issue';

    protected $customTypes = array(
        'issue196type' => Issue196Type::class
    );

    public function testIssue31()
    {
        $reve = new Issue31Reve();
        $reve->setTitre('reve');

        $this->persistManager->persist($reve);
        $this->persistManager->flush();

        $user = new Issue31User();
        $user->setTitre('user');
        $user->setReve($reve);

        $this->persistManager->persist($user);
        $this->persistManager->flush();

        $this->persistManager->remove($user);
        $this->persistManager->flush();
    }

    public function testIssue111()
    {
        $this->persistManager->getEventManager()->addEventSubscriber(new \Gedmo\SoftDeleteable\SoftDeleteableListener());

        $e = new Issue111Entity();
        $e->setStatus('test status');

        $this->persistManager->persist($e);
        $this->persistManager->flush($e); //#1

        $this->persistManager->remove($e);
        $this->persistManager->flush(); //#2

        $revision = $this->getRevision(2);
        $ae = $this->objectAuditManager->findObjectByRevision(Issue111Entity::class, 1, $revision);

        $this->assertInstanceOf(DateTime::class, $ae->getDeletedAt());
    }

    public function testEscapedColumns()
    {
        $e = new EscapedColumnsEntity();
        $e->setLeft(1);
        $e->setLft(2);
        $this->persistManager->persist($e);
        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        $this->objectAuditManager->findObjectByRevision(get_class($e), $e->getId(), $revision);
    }

    public function testIssue87()
    {
        $org = new Issue87Organization();
        $project = new Issue87Project();
        $project->setOrganisation($org);
        $project->setSomeProperty('some property');
        $project->setTitle('test project');
        $comment = new Issue87ProjectComment();
        $comment->setProject($project);
        $comment->setText('text comment');

        $this->persistManager->persist($org);
        $this->persistManager->persist($project);
        $this->persistManager->persist($comment);
        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        $auditedProject = $this->objectAuditManager->findObjectByRevision(get_class($project), $project->getId(), $revision);

        $this->assertEquals($org->getId(), $auditedProject->getOrganisation()->getId());
        $this->assertEquals('test project', $auditedProject->getTitle());
        $this->assertEquals('some property', $auditedProject->getSomeProperty());

        $auditedComment = $this->objectAuditManager->findObjectByRevision(get_class($comment), $comment->getId(), $revision);
        $this->assertEquals('test project', $auditedComment->getProject()->getTitle());

        $project->setTitle('changed project title');
        $this->persistManager->flush();

        $revision = $this->getRevision(2);
        $auditedComment = $this->objectAuditManager->findObjectByRevision(get_class($comment), $comment->getId(), $revision);
        $this->assertEquals('changed project title', $auditedComment->getProject()->getTitle());
    }

    public function testIssue9()
    {
        $address = new Issue9Address();
        $address->setAddressText('NY, Red Street 6');

        $customer = new Issue9Customer();
        $customer->setAddresses(array($address));
        $customer->setPrimaryAddress($address);

        $address->setCustomer($customer);

        $this->persistManager->persist($customer);
        $this->persistManager->persist($address);

        $this->persistManager->flush(); //#1

        $revision = $this->getRevision(1);
        $aAddress = $this->objectAuditManager->findObjectByRevision(get_class($address), $address->getId(), $revision);
        $this->assertEquals($customer->getId(), $aAddress->getCustomer()->getId());

        /** @var Issue9Customer $aCustomer */
        $aCustomer = $this->objectAuditManager->findObjectByRevision(get_class($customer), $customer->getId(), $revision);

        $this->assertNotNull($aCustomer->getPrimaryAddress());
        $this->assertEquals('NY, Red Street 6', $aCustomer->getPrimaryAddress()->getAddressText());
    }

    public function testDuplicateRevisionKeyConstraintFailure()
    {
        $primaryOwner = new DuplicateRevisionFailureTestPrimaryOwner();
        $this->persistManager->persist($primaryOwner);

        $secondaryOwner = new DuplicateRevisionFailureTestSecondaryOwner();
        $this->persistManager->persist($secondaryOwner);

        $primaryOwner->addSecondaryOwner($secondaryOwner);

        $element = new DuplicateRevisionFailureTestOwnedElement();
        $this->persistManager->persist($element);

        $primaryOwner->addElement($element);
        $secondaryOwner->addElement($element);

        $this->persistManager->flush();

        $this->persistManager->getUnitOfWork()->clear();

        $primaryOwner = $this->persistManager->find(DuplicateRevisionFailureTestPrimaryOwner::class, 1);

        $this->persistManager->remove($primaryOwner);
        $this->persistManager->flush();
    }

    public function testIssue156()
    {
        $client = new Issue156Client();

        $number = new Issue156ContactTelephoneNumber();
        $number->setNumber('0123567890');
        $client->addTelephoneNumber($number);

        $this->persistManager->persist($client);
        $this->persistManager->persist($number);
        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        $this->objectAuditManager->findObjectByRevision(get_class($number), $number->getId(), $revision);
    }

    public function testIssue194()
    {
        $user = new Issue194User();
        $address = new Issue194Address($user);

        $this->persistManager->persist($user);
        $this->persistManager->flush();
        $this->persistManager->persist($address);
        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        $auditUser = $this->objectAuditManager->findObjectByRevision(get_class($user), $user->getId(), $revision);

        $revision = $this->getRevision(2);
        $auditAddress = $this->objectAuditManager->findObjectByRevision(get_class($address), $address->getUser()->getId(), $revision);
        $this->assertEquals($auditAddress->getUser(), $auditUser);
        $this->assertEquals($address->getUser(), $auditUser);
        $this->assertEquals($auditAddress->getUser(), $user);
    }
    
    public function testIssue196()
    {
        $entity = new Issue196Entity();
        $entity->setSqlConversionField('THIS SHOULD BE LOWER CASE');
        $this->persistManager->persist($entity);
        $this->persistManager->flush();
        $this->persistManager->clear();

        $persistedEntity = $this->persistManager->find(get_class($entity), $entity->getId());

        $currentRevision = $this->objectAuditManager->getCurrentObjectRevision(get_class($entity), $entity->getId());
        $currentRevisionEntity = $this->objectAuditManager->findObjectByRevision(get_class($entity), $entity->getId(), $currentRevision);

        $this->assertEquals(
            $persistedEntity,
            $currentRevisionEntity,
            'Current revision of audited entity is not equivalent to persisted entity:'
        );
    }

    public function testIssue198()
    {
        $owner = new Issue198Owner();
        $car = new Issue198Car();
        
        $this->persistManager->persist($owner);
        $this->persistManager->persist($car);
        $this->persistManager->flush();
        
        $owner->addCar($car);

        $this->persistManager->persist($owner);
        $this->persistManager->persist($car);
        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        $car1 = $this->objectAuditManager->findObjectByRevision(get_class($car), $car->getId(), $revision);
        $this->assertNull($car1->getOwner());

        $revision = $this->getRevision(2);
        $car2 = $this->objectAuditManager->findObjectByRevision(get_class($car), $car->getId(), $revision);
        $this->assertEquals($car2->getOwner()->getId(), $owner->getId());
    }
}
