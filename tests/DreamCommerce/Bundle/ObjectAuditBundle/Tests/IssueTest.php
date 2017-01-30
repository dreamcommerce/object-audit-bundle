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

namespace DreamCommerce\Bundle\ObjectAuditBundle\Tests;

use DateTime;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\DuplicateRevisionFailureTestOwnedElement;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\DuplicateRevisionFailureTestPrimaryOwner;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\DuplicateRevisionFailureTestSecondaryOwner;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\EscapedColumnsEntity;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue111Entity;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue156Client;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue156Contact;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue156ContactTelephoneNumber;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue194Address;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue194User;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue196Entity;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue198Car;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue198Owner;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue31Reve;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue31User;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue87AbstractProject;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue87Organization;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue87Project;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue87ProjectComment;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue9Address;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Fixtures\Issue\Issue9Customer;
use DreamCommerce\Bundle\ObjectAuditBundle\Tests\Types\Issue196Type;

class IssueTest extends BaseTest
{
    protected $schemaEntities = array(
        EscapedColumnsEntity::class,
        Issue87Project::class,
        Issue87ProjectComment::class,
        Issue87AbstractProject::class,
        Issue87Organization::class,
        Issue9Address::class,
        Issue9Customer::class,
        Issue87Organization::class,
        DuplicateRevisionFailureTestPrimaryOwner::class,
        DuplicateRevisionFailureTestSecondaryOwner::class,
        DuplicateRevisionFailureTestOwnedElement::class,
        Issue111Entity::class,
        Issue31User::class,
        Issue31Reve::class,
        Issue156Contact::class,
        Issue156ContactTelephoneNumber::class,
        Issue156Client::class,
        Issue194User::class,
        Issue194Address::class,
        Issue196Entity::class,
        Issue198Car::class,
        Issue198Owner::class,
    );

    protected $auditedEntities = array(
        EscapedColumnsEntity::class,
        Issue87Project::class,
        Issue87ProjectComment::class,
        Issue87AbstractProject::class,
        Issue87Organization::class,
        Issue9Address::class,
        Issue9Customer::class,
        Issue87Organization::class,
        DuplicateRevisionFailureTestPrimaryOwner::class,
        DuplicateRevisionFailureTestSecondaryOwner::class,
        DuplicateRevisionFailureTestOwnedElement::class,
        Issue111Entity::class,
        Issue31User::class,
        Issue31Reve::class,
        Issue156Contact::class,
        Issue156ContactTelephoneNumber::class,
        Issue156Client::class,
        Issue194User::class,
        Issue194Address::class,
        Issue196Entity::class,
        Issue198Car::class,
        Issue198Owner::class,
    );

    protected $customTypes = array(
        'issue196type' => Issue196Type::class
    );

    public function testIssue31()
    {
        $reve = new Issue31Reve();
        $reve->setTitre('reve');

        $this->em->persist($reve);
        $this->em->flush();

        $user = new Issue31User();
        $user->setTitre('user');
        $user->setReve($reve);

        $this->em->persist($user);
        $this->em->flush();

        $this->em->remove($user);
        $this->em->flush();
    }

    public function testIssue111()
    {
        $this->em->getEventManager()->addEventSubscriber(new \Gedmo\SoftDeleteable\SoftDeleteableListener());

        $e = new Issue111Entity();
        $e->setStatus('test status');

        $this->em->persist($e);
        $this->em->flush($e); //#1

        $this->em->remove($e);
        $this->em->flush(); //#2

        $revision = $this->getRevision(2);
        $ae = $this->auditManager->findObjectByRevision(Issue111Entity::class, 1, $revision);

        $this->assertInstanceOf(DateTime::class, $ae->getDeletedAt());
    }

    public function testEscapedColumns()
    {
        $e = new EscapedColumnsEntity();
        $e->setLeft(1);
        $e->setLft(2);
        $this->em->persist($e);
        $this->em->flush();

        $revision = $this->getRevision(1);
        $this->auditManager->findObjectByRevision(get_class($e), $e->getId(), $revision);
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

        $this->em->persist($org);
        $this->em->persist($project);
        $this->em->persist($comment);
        $this->em->flush();

        $revision = $this->getRevision(1);
        $auditedProject = $this->auditManager->findObjectByRevision(get_class($project), $project->getId(), $revision);

        $this->assertEquals($org->getId(), $auditedProject->getOrganisation()->getId());
        $this->assertEquals('test project', $auditedProject->getTitle());
        $this->assertEquals('some property', $auditedProject->getSomeProperty());

        $auditedComment = $this->auditManager->findObjectByRevision(get_class($comment), $comment->getId(), $revision);
        $this->assertEquals('test project', $auditedComment->getProject()->getTitle());

        $project->setTitle('changed project title');
        $this->em->flush();

        $revision = $this->getRevision(2);
        $auditedComment = $this->auditManager->findObjectByRevision(get_class($comment), $comment->getId(), $revision);
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

        $this->em->persist($customer);
        $this->em->persist($address);

        $this->em->flush(); //#1

        $revision = $this->getRevision(1);
        $aAddress = $this->auditManager->findObjectByRevision(get_class($address), $address->getId(), $revision);
        $this->assertEquals($customer->getId(), $aAddress->getCustomer()->getId());

        /** @var Issue9Customer $aCustomer */
        $aCustomer = $this->auditManager->findObjectByRevision(get_class($customer), $customer->getId(), $revision);

        $this->assertNotNull($aCustomer->getPrimaryAddress());
        $this->assertEquals('NY, Red Street 6', $aCustomer->getPrimaryAddress()->getAddressText());
    }

    public function testDuplicateRevisionKeyConstraintFailure()
    {
        $primaryOwner = new DuplicateRevisionFailureTestPrimaryOwner();
        $this->em->persist($primaryOwner);

        $secondaryOwner = new DuplicateRevisionFailureTestSecondaryOwner();
        $this->em->persist($secondaryOwner);

        $primaryOwner->addSecondaryOwner($secondaryOwner);

        $element = new DuplicateRevisionFailureTestOwnedElement();
        $this->em->persist($element);

        $primaryOwner->addElement($element);
        $secondaryOwner->addElement($element);

        $this->em->flush();

        $this->em->getUnitOfWork()->clear();

        $primaryOwner = $this->em->find(DuplicateRevisionFailureTestPrimaryOwner::class, 1);

        $this->em->remove($primaryOwner);
        $this->em->flush();
    }

    public function testIssue156()
    {
        $client = new Issue156Client();

        $number = new Issue156ContactTelephoneNumber();
        $number->setNumber('0123567890');
        $client->addTelephoneNumber($number);

        $this->em->persist($client);
        $this->em->persist($number);
        $this->em->flush();

        $revision = $this->getRevision(1);
        $this->auditManager->findObjectByRevision(get_class($number), $number->getId(), $revision);
    }

    public function testIssue194()
    {
        $user = new Issue194User();
        $address = new Issue194Address($user);

        $this->em->persist($user);
        $this->em->flush();
        $this->em->persist($address);
        $this->em->flush();

        $revision = $this->getRevision(1);
        $auditUser = $this->auditManager->findObjectByRevision(get_class($user), $user->getId(), $revision);

        $revision = $this->getRevision(2);
        $auditAddress = $this->auditManager->findObjectByRevision(get_class($address), $address->getUser()->getId(), $revision);
        $this->assertEquals($auditAddress->getUser(), $auditUser);
        $this->assertEquals($address->getUser(), $auditUser);
        $this->assertEquals($auditAddress->getUser(), $user);
    }
    
    public function testIssue196()
    {
        $entity = new Issue196Entity();
        $entity->setSqlConversionField('THIS SHOULD BE LOWER CASE');
        $this->em->persist($entity);
        $this->em->flush();
        $this->em->clear();

        $persistedEntity = $this->em->find(get_class($entity), $entity->getId());

        $currentRevision = $this->auditManager->getCurrentObjectRevision(get_class($entity), $entity->getId());
        $currentRevisionEntity = $this->auditManager->findObjectByRevision(get_class($entity), $entity->getId(), $currentRevision);

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
        
        $this->em->persist($owner);
        $this->em->persist($car);
        $this->em->flush();
        
        $owner->addCar($car);

        $this->em->persist($owner);
        $this->em->persist($car);
        $this->em->flush();

        $revision = $this->getRevision(1);
        $car1 = $this->auditManager->findObjectByRevision(get_class($car), $car->getId(), $revision);
        $this->assertNull($car1->getOwner());

        $revision = $this->getRevision(2);
        $car2 = $this->auditManager->findObjectByRevision(get_class($car), $car->getId(), $revision);
        $this->assertEquals($car2->getOwner()->getId(), $owner->getId());
    }
}
