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

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Model\ObjectAudit;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\CheeseProduct;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\ChildEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\DataContainerEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\DataLegalEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\DataPrivateEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\FoodCategory;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\OneToOneAuditedEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\OneToOneMasterEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\OneToOneNotAuditedEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\OwnedEntity1;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\OwnedEntity2;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\OwnedEntity3;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\OwnerEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\Page;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\PageAlias;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\PageLocalization;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\Product;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\RelatedEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\RelationFoobarEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\RelationOneToOneEntity;
use DreamCommerce\Tests\ObjectAudit\Fixtures\Relation\WineProduct;

class RelationTest extends BaseTest
{
    protected $fixturesPath = __DIR__ . '/Fixtures/Relation';

    public function testUndefinedIndexesInUOWForRelations()
    {
        $owner = new OwnerEntity();
        $owner->setTitle('owner');
        $owned1 = new OwnedEntity1();
        $owned1->setTitle('owned1');
        $owned1->setOwner($owner);
        $owned2 = new OwnedEntity2();
        $owned2->setTitle('owned2');
        $owned2->setOwner($owner);

        $this->persistManager->persist($owner);
        $this->persistManager->persist($owned1);
        $this->persistManager->persist($owned2);

        $this->persistManager->flush();

        unset($owner);
        unset($owned1);
        unset($owned2);
        $this->persistManager->clear();

        $owner = $this->persistManager->getReference(OwnerEntity::class, 1);
        $this->persistManager->remove($owner);
        $owned1 = $this->persistManager->getReference(OwnedEntity1::class, 1);
        $this->persistManager->remove($owned1);
        $owned2 = $this->persistManager->getReference(OwnedEntity2::class, 1);
        $this->persistManager->remove($owned2);

        $this->persistManager->flush();

        $revision = $this->getRevision(2);
        /** @var ObjectAudit[] $objects */
        $objects = $this->objectAuditManager->findAllObjectsChangedAtRevision($revision);

        $this->assertEquals(2, count($objects));
        $changedOwner = $objects[0]->getObject();
        $changedOwned = $objects[1]->getObject();
        if ($changedOwner instanceof OwnedEntity1) {
            $swap = $changedOwned;
            $changedOwned = $changedOwner;
            $changedOwner = $swap;
        }

        $this->assertContainsOnly(ObjectAudit::class, $objects);
        $this->assertInstanceOf(OwnerEntity::class, $changedOwner);
        $this->assertInstanceOf(OwnedEntity1::class, $changedOwned);
        $this->assertEquals(RevisionInterface::ACTION_DELETE, $objects[0]->getRevisionType());
        $this->assertEquals(RevisionInterface::ACTION_DELETE, $objects[1]->getRevisionType());
        $this->assertEquals(1, $changedOwner->getId());
        $this->assertEquals(1, $changedOwned->getId());
        //uninit proxy messes up ids, it is fine
        $this->assertCount(0, $changedOwner->getOwned1());
        $this->assertCount(0, $changedOwner->getOwned2());
        $this->assertNull($changedOwned->getOwner());
    }

    public function testIssue92()
    {
        $owner1 = new OwnerEntity();
        $owner1->setTitle('test');
        $owner2 = new OwnerEntity();
        $owner2->setTitle('test');

        $this->persistManager->persist($owner1);
        $this->persistManager->persist($owner2);

        $this->persistManager->flush();

        $owned1 = new OwnedEntity1();
        $owned1->setOwner($owner1);
        $owned1->setTitle('test');

        $owned2 = new OwnedEntity1();
        $owned2->setOwner($owner1);
        $owned2->setTitle('test');

        $owned3 = new OwnedEntity1();
        $owned3->setOwner($owner2);
        $owned3->setTitle('test');

        $this->persistManager->persist($owned1);
        $this->persistManager->persist($owned2);
        $this->persistManager->persist($owned3);

        $this->persistManager->flush();

        $owned2->setOwner($owner2);

        $this->persistManager->flush(); //3

        $revision = $this->getRevision(3);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);

        $this->assertCount(1, $audited->getOwned1());
    }

    public function testOneToOne()
    {
        $master = new OneToOneMasterEntity();
        $master->setTitle('master#1');

        $this->persistManager->persist($master);
        $this->persistManager->flush(); //#1

        $notAudited = new OneToOneNotAuditedEntity();
        $notAudited->setTitle('notaudited');

        $this->persistManager->persist($notAudited);

        $master->setNotAudited($notAudited);

        $this->persistManager->flush(); //#2

        $audited = new OneToOneAuditedEntity();
        $audited->setTitle('audited');
        $master->setAudited($audited);

        $this->persistManager->persist($audited);

        $this->persistManager->flush(); //#3

        $audited->setTitle('changed#4');

        $this->persistManager->flush(); //#4

        $master->setTitle('changed#5');

        $this->persistManager->flush(); //#5

        $this->persistManager->remove($audited);

        $this->persistManager->flush(); //#6

        $revision = $this->getRevision(1);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('master#1', $audited->getTitle());
        $this->assertEquals(null, $audited->getAudited());
        $this->assertEquals(null, $audited->getNotAudited());

        $revision = $this->getRevision(2);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('master#1', $audited->getTitle());
        $this->assertEquals(null, $audited->getAudited());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $revision = $this->getRevision(3);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('master#1', $audited->getTitle());
        $this->assertEquals('audited', $audited->getAudited()->getTitle());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $revision = $this->getRevision(4);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('master#1', $audited->getTitle());
        $this->assertEquals('changed#4', $audited->getAudited()->getTitle());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $configuration = $this->objectAuditManager->getConfiguration();
        $configuration->setLoadAuditedObjects(false);
        $this->objectAuditFactory->clearAuditCache();

        $audited = $this->objectAuditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals(null, $audited->getAudited());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $configuration->setLoadAuditedObjects(true);
        $configuration->setLoadNativeObjects(false);
        $this->objectAuditFactory->clearAuditCache();

        $audited = $this->objectAuditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('changed#4', $audited->getAudited()->getTitle());
        $this->assertEquals(null, $audited->getNotAudited());

        $configuration->setLoadNativeObjects(true);

        $revision = $this->getRevision(5);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('changed#5', $audited->getTitle());
        $this->assertEquals('changed#4', $audited->getAudited()->getTitle());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $revision = $this->getRevision(6);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('changed#5', $audited->getTitle());
        $this->assertEquals(null, $audited->getAudited());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());
    }

    /**
     * This test verifies the temporary behaviour of audited entities with M-M relationships
     * until https://github.com/simplethings/EntityAudit/issues/85 is implemented
     */
    public function testManyToMany()
    {
        $owner = new OwnerEntity();
        $owner->setTitle('owner#1');

        $owned31 = new OwnedEntity3();
        $owned31->setTitle('owned3#1');
        $owner->addOwned3($owned31);

        $owned32 = new OwnedEntity3();
        $owned32->setTitle('owned3#2');
        $owner->addOwned3($owned32);

        $this->persistManager->persist($owner);
        $this->persistManager->persist($owned31);
        $this->persistManager->persist($owned32);

        $this->persistManager->flush(); //#1

        $revision = $this->getRevision(1);

        //checking that getOwned3() returns an empty collection
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertInstanceOf(Collection::class, $audited->getOwned3());
        $this->assertCount(0, $audited->getOwned3());
    }

    /**
     * @group mysql
     */
    public function testRelations()
    {
        //create owner
        $owner = new OwnerEntity();
        $owner->setTitle('rev#1');

        $this->persistManager->persist($owner);
        $this->persistManager->flush();

        $this->assertCount(1, $this->objectAuditManager->findObjectRevisions(get_class($owner), $owner->getId()));

        //create un-managed entity
        $owned21 = new OwnedEntity2();
        $owned21->setTitle('owned21');
        $owned21->setOwner($owner);

        $this->persistManager->persist($owned21);
        $this->persistManager->flush();

        //should not add a revision
        $this->assertCount(1, $this->objectAuditManager->findObjectRevisions(get_class($owner), $owner->getId()));

        $owner->setTitle('changed#2');

        $this->persistManager->flush();

        //should add a revision
        $this->assertCount(2, $this->objectAuditManager->findObjectRevisions(get_class($owner), $owner->getId()));

        $owned11 = new OwnedEntity1();
        $owned11->setTitle('created#3');
        $owned11->setOwner($owner);

        $this->persistManager->persist($owned11);

        $this->persistManager->flush();

        //should not add a revision for owner
        $this->assertCount(2, $this->objectAuditManager->findObjectRevisions(get_class($owner), $owner->getId()));
        //should add a revision for owned
        $this->assertCount(1, $this->objectAuditManager->findObjectRevisions(get_class($owned11), $owned11->getId()));

        //should not mess foreign keys
        $rows = $this->persistManager->getConnection()->fetchAll('SELECT strange_owned_id_name FROM OwnedEntity1');
        $this->assertEquals($owner->getId(), $rows[0]['strange_owned_id_name']);
        $this->persistManager->refresh($owner);
        $this->assertCount(1, $owner->getOwned1());
        $this->assertCount(1, $owner->getOwned2());

        //we have a third revision where Owner with title changed#2 has one owned2 and one owned1 entity with title created#3
        $owned12 = new OwnedEntity1();
        $owned12->setTitle('created#4');
        $owned12->setOwner($owner);

        $this->persistManager->persist($owned12);
        $this->persistManager->flush();

        //we have a forth revision where Owner with title changed#2 has one owned2 and two owned1 entities (created#3, created#4)
        $owner->setTitle('changed#5');

        $this->persistManager->flush();
        //we have a fifth revision where Owner with title changed#5 has one owned2 and two owned1 entities (created#3, created#4)

        $owner->setTitle('changed#6');
        $owned12->setTitle('changed#6');

        $this->persistManager->flush();

        $this->persistManager->remove($owned11);
        $owned12->setTitle('changed#7');
        $owner->setTitle('changed#7');
        $this->persistManager->flush();
        //we have a seventh revision where Owner with title changed#7 has one owned2 and one owned1 entity (changed#7)

        //checking third revision
        $revision = $this->getRevision(3);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertInstanceOf(Collection::class, $audited->getOwned2());
        $this->assertEquals('changed#2', $audited->getTitle());
        $this->assertCount(1, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $o1 = $audited->getOwned1();
        $this->assertEquals('created#3', $o1[0]->getTitle());
        $o2 = $audited->getOwned2();
        $this->assertEquals('owned21', $o2[0]->getTitle());

        //checking forth revision
        $revision = $this->getRevision(4);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertEquals('changed#2', $audited->getTitle());
        $this->assertCount(2, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $o1 = $audited->getOwned1();
        $this->assertEquals('created#3', $o1[0]->getTitle());
        $this->assertEquals('created#4', $o1[1]->getTitle());
        $o2 = $audited->getOwned2();
        $this->assertEquals('owned21', $o2[0]->getTitle());

        //check skipping collections

        $configuration = $this->objectAuditManager->getConfiguration();
        $configuration->setLoadAuditedCollections(false);
        $this->objectAuditFactory->clearAuditCache();

        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertCount(0, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());

        $configuration->setLoadNativeCollections(false);
        $configuration->setLoadAuditedCollections(true);
        $this->objectAuditFactory->clearAuditCache();

        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertCount(2, $audited->getOwned1());
        $this->assertCount(0, $audited->getOwned2());

        //checking fifth revision
        $configuration->setLoadNativeCollections(true);
        $this->objectAuditFactory->clearAuditCache();

        $revision = $this->getRevision(5);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertEquals('changed#5', $audited->getTitle());
        $this->assertCount(2, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $o1 = $audited->getOwned1();
        $this->assertEquals('created#3', $o1[0]->getTitle());
        $this->assertEquals('created#4', $o1[1]->getTitle());
        $o2 = $audited->getOwned2();
        $this->assertEquals('owned21', $o2[0]->getTitle());

        //checking sixth revision
        $revision = $this->getRevision(6);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertEquals('changed#6', $audited->getTitle());
        $this->assertCount(2, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $o1 = $audited->getOwned1();
        $this->assertEquals('created#3', $o1[0]->getTitle());
        $this->assertEquals('changed#6', $o1[1]->getTitle());
        $o2 = $audited->getOwned2();
        $this->assertEquals('owned21', $o2[0]->getTitle());

        //checking seventh revision
        $revision = $this->getRevision(7);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertEquals('changed#7', $audited->getTitle());
        $this->assertCount(1, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $o1 = $audited->getOwned1();
        $this->assertEquals('changed#7', $o1[0]->getTitle());
        $o2 = $audited->getOwned2();
        $this->assertEquals('owned21', $o2[0]->getTitle());

        $history = $this->objectAuditManager->getObjectHistory(get_class($owner), $owner->getId());
        $this->assertContainsOnly(ObjectAudit::class, $history);

        $this->assertCount(5, $history);
    }

    /**
     * @group mysql
     */
    public function testRemoval()
    {
        $owner1 = new OwnerEntity();
        $owner1->setTitle('owner1');

        $owner2 = new OwnerEntity();
        $owner2->setTitle('owner2');

        $owned1 = new OwnedEntity1();
        $owned1->setTitle('owned1');
        $owned1->setOwner($owner1);

        $owned2 = new OwnedEntity1();
        $owned2->setTitle('owned2');
        $owned2->setOwner($owner1);

        $owned3 = new OwnedEntity1();
        $owned3->setTitle('owned3');
        $owned3->setOwner($owner1);

        $this->persistManager->persist($owner1);
        $this->persistManager->persist($owner2);
        $this->persistManager->persist($owned1);
        $this->persistManager->persist($owned2);
        $this->persistManager->persist($owned3);

        $this->persistManager->flush(); //#1

        $owned1->setOwner($owner2);
        $this->persistManager->flush(); //#2

        $this->persistManager->remove($owned1);
        $this->persistManager->flush(); //#3

        $owned2->setTitle('updated owned2');
        $this->persistManager->flush(); //#4

        $this->persistManager->remove($owned2);
        $this->persistManager->flush(); //#5

        $this->persistManager->remove($owned3);
        $this->persistManager->flush(); //#6

        $revision = $this->getRevision(1);
        $owner = $this->objectAuditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(3, $owner->getOwned1());

        $revision = $this->getRevision(2);
        $owner = $this->objectAuditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(2, $owner->getOwned1());

        $revision = $this->getRevision(3);
        $owner = $this->objectAuditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(2, $owner->getOwned1());

        $revision = $this->getRevision(4);
        $owner = $this->objectAuditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(2, $owner->getOwned1());

        $revision = $this->getRevision(5);
        $owner = $this->objectAuditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(1, $owner->getOwned1());

        $revision = $this->getRevision(6);
        $owner = $this->objectAuditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(0, $owner->getOwned1());
    }

    /**
     * @group mysql
     */
    public function testDetaching()
    {
        $owner = new OwnerEntity();
        $owner->setTitle('created#1');

        $owned = new OwnedEntity1();
        $owned->setTitle('created#1');

        $this->persistManager->persist($owner);
        $this->persistManager->persist($owned);

        $this->persistManager->flush(); //#1

        $ownerId1 = $owner->getId();
        $ownedId1 = $owned->getId();

        $owned->setTitle('associated#2');
        $owned->setOwner($owner);

        $this->persistManager->flush(); //#2

        $owned->setTitle('deassociated#3');
        $owned->setOwner(null);

        $this->persistManager->flush(); //#3

        $owned->setTitle('associated#4');
        $owned->setOwner($owner);

        $this->persistManager->flush(); //#4

        $this->persistManager->remove($owned);

        $this->persistManager->flush(); //#5

        $owned = new OwnedEntity1();
        $owned->setTitle('recreated#6');
        $owned->setOwner($owner);

        $this->persistManager->persist($owned);
        $this->persistManager->flush(); //#6

        $ownedId2 = $owned->getId();

        $this->persistManager->remove($owner);
        $this->persistManager->flush(); //#7

        $revision = $this->getRevision(1);
        $auditedEntity = $this->objectAuditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $this->assertEquals('created#1', $auditedEntity->getTitle());
        $this->assertCount(0, $auditedEntity->getOwned1());

        $revision = $this->getRevision(2);
        $auditedEntity = $this->objectAuditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $o1 = $auditedEntity->getOwned1();
        $this->assertCount(1, $o1);
        $this->assertEquals($ownedId1, $o1[0]->getId());

        $revision = $this->getRevision(3);
        $auditedEntity = $this->objectAuditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $this->assertCount(0, $auditedEntity->getOwned1());

        $revision = $this->getRevision(4);
        $auditedEntity = $this->objectAuditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $this->assertCount(1, $auditedEntity->getOwned1());

        $revision = $this->getRevision(5);
        $auditedEntity = $this->objectAuditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $this->assertCount(0, $auditedEntity->getOwned1());

        $revision = $this->getRevision(6);
        $auditedEntity = $this->objectAuditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $o1 = $auditedEntity->getOwned1();
        $this->assertCount(1, $o1);
        $this->assertEquals($ownedId2, $o1[0]->getId());

        $revision = $this->getRevision(7);
        $auditedEntity = $this->objectAuditManager->findObjectByRevision(get_class($owned), $ownedId2, $revision);
        $this->assertEquals(null, $auditedEntity->getOwner());
    }

    public function testOneXRelations()
    {
        $owner = new OwnerEntity();
        $owner->setTitle('owner');

        $owned = new OwnedEntity1();
        $owned->setTitle('owned');
        $owned->setOwner($owner);

        $this->persistManager->persist($owner);
        $this->persistManager->persist($owned);

        $this->persistManager->flush();
        //first revision done

        $owner->setTitle('changed#2');
        $owned->setTitle('changed#2');
        $this->persistManager->flush();

        //checking first revision
        $revision = $this->getRevision(1);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owned), $owner->getId(), $revision);
        $this->assertEquals('owned', $audited->getTitle());
        $this->assertEquals('owner', $audited->getOwner()->getTitle());

        //checking second revision
        $revision = $this->getRevision(2);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owned), $owner->getId(), $revision);

        $this->assertEquals('changed#2', $audited->getTitle());
        $this->assertEquals('changed#2', $audited->getOwner()->getTitle());
    }

    public function testOneToManyJoinedInheritance()
    {
        $food = new FoodCategory();
        $this->persistManager->persist($food);

        $parmesanCheese = new CheeseProduct('Parmesan');
        $this->persistManager->persist($parmesanCheese);

        $cheddarCheese = new CheeseProduct('Cheddar');
        $this->persistManager->persist($cheddarCheese);

        $vine = new WineProduct('Champagne');
        $this->persistManager->persist($vine);

        $food->addProduct($parmesanCheese);
        $food->addProduct($cheddarCheese);
        $food->addProduct($vine);

        $this->persistManager->flush();

        /** @var FoodCategory $auditedFood */
        $auditedFood = $this->objectAuditManager->findObjectByRevision(
            get_class($food),
            $food->getId(),
            $this->objectAuditManager->getCurrentObjectRevision(get_class($food), $food->getId())
        );

        $this->assertInstanceOf(get_class($food), $auditedFood);
        $products = $auditedFood->getProducts();
        $this->assertCount(3, $products);

        $this->assertInstanceOf(get_class($parmesanCheese), $products[0]);
        $this->assertInstanceOf(get_class($cheddarCheese), $products[1]);
        $this->assertInstanceOf(get_class($vine), $products[2]);

        $this->assertEquals('Parmesan', $products[0]->getName());
        $this->assertEquals('Cheddar', $products[1]->getName());
        $this->assertEquals('Champagne', $products[2]->getName());

        $this->assertEquals($parmesanCheese->getId(), $products[0]->getId());
        $this->assertEquals($cheddarCheese->getId(), $products[1]->getId());
    }

    public function testOneToManyWithIndexBy()
    {
        $page = new Page();
        $this->persistManager->persist($page);

        $gbLocalization = new PageLocalization('en-GB');
        $this->persistManager->persist($gbLocalization);

        $usLocalization = new PageLocalization('en-US');
        $this->persistManager->persist($usLocalization);

        $page->addLocalization($gbLocalization);
        $page->addLocalization($usLocalization);

        $this->persistManager->flush();

        $auditedPage = $this->objectAuditManager->findObjectByRevision(
            get_class($page),
            $page->getId(),
            $this->objectAuditManager->getCurrentObjectRevision(get_class($page), $page->getId())
        );

        $this->assertNotEmpty($auditedPage->getLocalizations());

        $this->assertCount(2, $auditedPage->getLocalizations());

        $this->assertNotEmpty($auditedPage->getLocalizations()->get('en-US'));
        $this->assertNotEmpty($auditedPage->getLocalizations()->get('en-GB'));
    }

    /**
     * @group mysql
     */
    public function testOneToManyCollectionDeletedElements()
    {
        $owner = new OwnerEntity();
        $this->persistManager->persist($owner);

        $ownedOne = new OwnedEntity1();
        $ownedOne->setTitle('Owned#1');
        $ownedOne->setOwner($owner);
        $this->persistManager->persist($ownedOne);

        $ownedTwo = new OwnedEntity1();
        $ownedTwo->setTitle('Owned#2');
        $ownedTwo->setOwner($owner);
        $this->persistManager->persist($ownedTwo);

        $ownedThree = new OwnedEntity1();
        $ownedThree->setTitle('Owned#3');
        $ownedThree->setOwner($owner);
        $this->persistManager->persist($ownedThree);

        $ownedFour = new OwnedEntity1();
        $ownedFour->setTitle('Owned#4');
        $ownedFour->setOwner($owner);
        $this->persistManager->persist($ownedFour);

        $owner->addOwned1($ownedOne);
        $owner->addOwned1($ownedTwo);
        $owner->addOwned1($ownedThree);
        $owner->addOwned1($ownedFour);

        $owner->setTitle('Owner with four owned elements.');
        $this->persistManager->flush(); //#1

        $owner->setTitle('Owner with three owned elements.');
        $this->persistManager->remove($ownedTwo);

        $this->persistManager->flush(); //#2

        $owner->setTitle('Just another revision.');

        $this->persistManager->flush(); //#3

        $auditedOwner = $this->objectAuditManager->findObjectByRevision(
            get_class($owner),
            $owner->getId(),
            $this->objectAuditManager->getCurrentObjectRevision(get_class($owner), $owner->getId())
        );

        $this->assertCount(3, $auditedOwner->getOwned1());

        $ids = array();
        foreach ($auditedOwner->getOwned1() as $ownedElement) {
            $ids[] = $ownedElement->getId();
        }

        $this->assertTrue(in_array($ownedOne->getId(), $ids));
        $this->assertTrue(in_array($ownedThree->getId(), $ids));
        $this->assertTrue(in_array($ownedFour->getId(), $ids));
    }

    public function testOneToOneEdgeCase()
    {
        $base = new RelationOneToOneEntity();

        $referenced = new RelationFoobarEntity();
        $referenced->setFoobarField('foobar');
        $referenced->setReferencedField('referenced');

        $base->setReferencedEntity($referenced);
        $referenced->setOneToOne($base);

        $this->persistManager->persist($base);
        $this->persistManager->persist($referenced);

        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        $auditedBase = $this->objectAuditManager->findObjectByRevision(get_class($base), $base->getId(), $revision);

        $this->assertEquals('foobar', $auditedBase->getReferencedEntity()->getFoobarField());
        $this->assertEquals('referenced', $auditedBase->getReferencedEntity()->getReferencedField());
    }

    /**
     * Specific test for the case where a join condition is via an ORM/Id and where the column is also an object.
     * Used to result in an 'array to string conversion' error
     */
    public function testJoinOnObject()
    {
        $page = new Page();
        $this->persistManager->persist($page);
        $this->persistManager->flush();

        $pageAlias = new PageAlias($page, 'This is the alias');
        $this->persistManager->persist($pageAlias);
        $this->persistManager->flush();
    }

    public function testOneToOneBidirectional()
    {
        $private1 = new DataPrivateEntity();
        $private1->setName('private1');

        $legal1 = new DataLegalEntity();
        $legal1->setCompany('legal1');

        $legal2 = new DataLegalEntity();
        $legal2->setCompany('legal2');

        $container1 = new DataContainerEntity();
        $container1->setData($private1);
        $container1->setName('container1');

        $container2 = new DataContainerEntity();
        $container2->setData($legal1);
        $container2->setName('container2');

        $container3 = new DataContainerEntity();
        $container3->setData($legal2);
        $container3->setName('container3');

        $this->persistManager->persist($container1);
        $this->persistManager->persist($container2);
        $this->persistManager->persist($container3);
        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        $legal2Base = $this->objectAuditManager->findObjectByRevision(get_class($legal2), $legal2->getId(), $revision);

        $this->assertEquals('container3', $legal2Base->getDataContainer()->getName());
    }

    public function testDiff()
    {
        $owner1 = new OwnerEntity();
        $owner1->setTitle('Owner 1');
        $owner2 = new OwnerEntity();
        $owner2->setTitle('Owner 2');
        $this->persistManager->persist($owner1);
        $this->persistManager->persist($owner2);

        $owned = new OwnedEntity1();
        $owned->setTitle('Owned');
        $this->persistManager->persist($owned);
        $owned->setOwner($owner1);
        $this->persistManager->flush();

        $owned->setOwner($owner2);
        $this->persistManager->persist($owned);
        $this->persistManager->flush();

        $revision1 = $this->getRevision(1);
        $revision2 = $this->getRevision(2);

        $diff = $this->objectAuditManager->diffObjectRevisions(get_class($owned), 1, $revision1, $revision2);

        $this->assertSame($owner1->getTitle(), $diff['owner']['old']->getTitle());
        $this->assertSame($owner2->getTitle(), $diff['owner']['new']->getTitle());
        $this->assertEmpty($diff['owner']['same']);
    }

    public function testDoubleFieldDefinitionEdgeCase()
    {
        $owner = new ChildEntity();
        $owned = new RelatedEntity();

        $this->persistManager->persist($owner);
        $this->persistManager->persist($owned);
        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertInstanceOf(get_class($owner), $audited);

        $owner->setRelation($owned);
        $this->persistManager->flush();

        $revision = $this->getRevision(2);
        $audited = $this->objectAuditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertInstanceOf(get_class($owner), $audited);
    }
}
