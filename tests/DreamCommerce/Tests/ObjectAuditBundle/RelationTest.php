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

namespace DreamCommerce\Tests\ObjectAuditBundle;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Model\ChangedObject;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\AbstractDataEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\Category;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\CheeseProduct;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\ChildEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\DataContainerEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\DataLegalEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\DataPrivateEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\FoodCategory;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\OneToOneAuditedEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\OneToOneMasterEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\OneToOneNotAuditedEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\OwnedEntity1;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\OwnedEntity2;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\OwnedEntity3;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\OwnerEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\Page;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\PageAlias;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\PageLocalization;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\Product;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\RelatedEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\RelationFoobarEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\RelationOneToOneEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\RelationReferencedEntity;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Relation\WineProduct;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\RevisionTest;

class RelationTest extends BaseTest
{
    protected $schemaEntities = array(
        OwnerEntity::class,
        OwnedEntity1::class,
        OwnedEntity2::class,
        OwnedEntity3::class,
        OneToOneMasterEntity::class,
        OneToOneAuditedEntity::class,
        OneToOneNotAuditedEntity::class,
        Category::class,
        FoodCategory::class,
        Product::class,
        WineProduct::class,
        CheeseProduct::class,
        Page::class,
        PageAlias::class,
        PageLocalization::class,
        RelationOneToOneEntity::class,
        RelationFoobarEntity::class,
        RelationReferencedEntity::class,
        AbstractDataEntity::class,
        DataLegalEntity::class,
        DataPrivateEntity::class,
        DataContainerEntity::class,
        RelationReferencedEntity::class,
        ChildEntity::class,
        RelatedEntity::class,
        RevisionTest::class
    );

    protected $auditedEntities = array(
        OwnerEntity::class,
        OwnedEntity1::class,
        OneToOneAuditedEntity::class,
        OneToOneMasterEntity::class,
        Category::class,
        FoodCategory::class,
        Product::class,
        WineProduct::class,
        CheeseProduct::class,
        Page::class,
        PageAlias::class,
        PageLocalization::class,
        RelationOneToOneEntity::class,
        RelationFoobarEntity::class,
        RelationReferencedEntity::class,
        AbstractDataEntity::class,
        DataLegalEntity::class,
        DataPrivateEntity::class,
        DataContainerEntity::class,
        RelationReferencedEntity::class,
        ChildEntity::class,
        RelatedEntity::class,
    );

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

        $this->em->persist($owner);
        $this->em->persist($owned1);
        $this->em->persist($owned2);

        $this->em->flush();

        unset($owner);
        unset($owned1);
        unset($owned2);
        $this->em->clear();

        $owner = $this->em->getReference(OwnerEntity::class, 1);
        $this->em->remove($owner);
        $owned1 = $this->em->getReference(OwnedEntity1::class, 1);
        $this->em->remove($owned1);
        $owned2 = $this->em->getReference(OwnedEntity2::class, 1);
        $this->em->remove($owned2);

        $this->em->flush();

        $revision = $this->getRevision(2);
        /** @var ChangedObject[] $changedEntities */
        $changedEntities = $this->auditManager->findAllObjectsChangedAtRevision($revision);

        $this->assertEquals(2, count($changedEntities));
        $changedOwner = $changedEntities[0]->getObject();
        $changedOwned = $changedEntities[1]->getObject();

        $this->assertContainsOnly(ChangedObject::class, $changedEntities);
        $this->assertInstanceOf(OwnerEntity::class, $changedOwner);
        $this->assertInstanceOf(OwnedEntity1::class, $changedOwned);
        $this->assertEquals(RevisionInterface::ACTION_DELETE, $changedEntities[0]->getRevisionType());
        $this->assertEquals(RevisionInterface::ACTION_DELETE, $changedEntities[1]->getRevisionType());
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

        $this->em->persist($owner1);
        $this->em->persist($owner2);

        $this->em->flush();

        $owned1 = new OwnedEntity1();
        $owned1->setOwner($owner1);
        $owned1->setTitle('test');

        $owned2 = new OwnedEntity1();
        $owned2->setOwner($owner1);
        $owned2->setTitle('test');

        $owned3 = new OwnedEntity1();
        $owned3->setOwner($owner2);
        $owned3->setTitle('test');

        $this->em->persist($owned1);
        $this->em->persist($owned2);
        $this->em->persist($owned3);

        $this->em->flush();

        $owned2->setOwner($owner2);

        $this->em->flush(); //3

        $revision = $this->getRevision(3);
        $audited = $this->auditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);

        $this->assertCount(1, $audited->getOwned1());
    }

    public function testOneToOne()
    {
        $master = new OneToOneMasterEntity();
        $master->setTitle('master#1');

        $this->em->persist($master);
        $this->em->flush(); //#1

        $notAudited = new OneToOneNotAuditedEntity();
        $notAudited->setTitle('notaudited');

        $this->em->persist($notAudited);

        $master->setNotAudited($notAudited);

        $this->em->flush(); //#2

        $audited = new OneToOneAuditedEntity();
        $audited->setTitle('audited');
        $master->setAudited($audited);

        $this->em->persist($audited);

        $this->em->flush(); //#3

        $audited->setTitle('changed#4');

        $this->em->flush(); //#4

        $master->setTitle('changed#5');

        $this->em->flush(); //#5

        $this->em->remove($audited);

        $this->em->flush(); //#6

        $revision = $this->getRevision(1);
        $audited = $this->auditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('master#1', $audited->getTitle());
        $this->assertEquals(null, $audited->getAudited());
        $this->assertEquals(null, $audited->getNotAudited());

        $revision = $this->getRevision(2);
        $audited = $this->auditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('master#1', $audited->getTitle());
        $this->assertEquals(null, $audited->getAudited());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $revision = $this->getRevision(3);
        $audited = $this->auditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('master#1', $audited->getTitle());
        $this->assertEquals('audited', $audited->getAudited()->getTitle());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $revision = $this->getRevision(4);
        $audited = $this->auditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('master#1', $audited->getTitle());
        $this->assertEquals('changed#4', $audited->getAudited()->getTitle());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $configuration = $this->auditManager->getConfiguration();
        $configuration->setLoadAuditedObjects(false);
        $this->objectAuditFactory->clearAuditCache();

        $audited = $this->auditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals(null, $audited->getAudited());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $configuration->setLoadAuditedObjects(true);
        $configuration->setLoadNativeObjects(false);
        $this->objectAuditFactory->clearAuditCache();

        $audited = $this->auditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('changed#4', $audited->getAudited()->getTitle());
        $this->assertEquals(null, $audited->getNotAudited());

        $configuration->setLoadNativeObjects(true);

        $revision = $this->getRevision(5);
        $audited = $this->auditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
        $this->assertEquals('changed#5', $audited->getTitle());
        $this->assertEquals('changed#4', $audited->getAudited()->getTitle());
        $this->assertEquals('notaudited', $audited->getNotAudited()->getTitle());

        $revision = $this->getRevision(6);
        $audited = $this->auditManager->findObjectByRevision(get_class($master), $master->getId(), $revision);
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

        $this->em->persist($owner);
        $this->em->persist($owned31);
        $this->em->persist($owned32);

        $this->em->flush(); //#1

        $revision = $this->getRevision(1);

        //checking that getOwned3() returns an empty collection
        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
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

        $this->em->persist($owner);
        $this->em->flush();

        $this->assertCount(1, $this->auditManager->findObjectRevisions(get_class($owner), $owner->getId()));

        //create un-managed entity
        $owned21 = new OwnedEntity2();
        $owned21->setTitle('owned21');
        $owned21->setOwner($owner);

        $this->em->persist($owned21);
        $this->em->flush();

        //should not add a revision
        $this->assertCount(1, $this->auditManager->findObjectRevisions(get_class($owner), $owner->getId()));

        $owner->setTitle('changed#2');

        $this->em->flush();

        //should add a revision
        $this->assertCount(2, $this->auditManager->findObjectRevisions(get_class($owner), $owner->getId()));

        $owned11 = new OwnedEntity1();
        $owned11->setTitle('created#3');
        $owned11->setOwner($owner);

        $this->em->persist($owned11);

        $this->em->flush();

        //should not add a revision for owner
        $this->assertCount(2, $this->auditManager->findObjectRevisions(get_class($owner), $owner->getId()));
        //should add a revision for owned
        $this->assertCount(1, $this->auditManager->findObjectRevisions(get_class($owned11), $owned11->getId()));

        //should not mess foreign keys
        $rows = $this->em->getConnection()->fetchAll('SELECT strange_owned_id_name FROM OwnedEntity1');
        $this->assertEquals($owner->getId(), $rows[0]['strange_owned_id_name']);
        $this->em->refresh($owner);
        $this->assertCount(1, $owner->getOwned1());
        $this->assertCount(1, $owner->getOwned2());

        //we have a third revision where Owner with title changed#2 has one owned2 and one owned1 entity with title created#3
        $owned12 = new OwnedEntity1();
        $owned12->setTitle('created#4');
        $owned12->setOwner($owner);

        $this->em->persist($owned12);
        $this->em->flush();

        //we have a forth revision where Owner with title changed#2 has one owned2 and two owned1 entities (created#3, created#4)
        $owner->setTitle('changed#5');

        $this->em->flush();
        //we have a fifth revision where Owner with title changed#5 has one owned2 and two owned1 entities (created#3, created#4)

        $owner->setTitle('changed#6');
        $owned12->setTitle('changed#6');

        $this->em->flush();

        $this->em->remove($owned11);
        $owned12->setTitle('changed#7');
        $owner->setTitle('changed#7');
        $this->em->flush();
        //we have a seventh revision where Owner with title changed#7 has one owned2 and one owned1 entity (changed#7)

        //checking third revision
        $revision = $this->getRevision(3);
        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
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
        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertEquals('changed#2', $audited->getTitle());
        $this->assertCount(2, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $o1 = $audited->getOwned1();
        $this->assertEquals('created#3', $o1[0]->getTitle());
        $this->assertEquals('created#4', $o1[1]->getTitle());
        $o2 = $audited->getOwned2();
        $this->assertEquals('owned21', $o2[0]->getTitle());

        //check skipping collections

        $configuration = $this->auditManager->getConfiguration();
        $configuration->setLoadAuditedCollections(false);
        $this->objectAuditFactory->clearAuditCache();

        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertCount(0, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());

        $configuration->setLoadNativeCollections(false);
        $configuration->setLoadAuditedCollections(true);
        $this->objectAuditFactory->clearAuditCache();

        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertCount(2, $audited->getOwned1());
        $this->assertCount(0, $audited->getOwned2());

        //checking fifth revision
        $configuration->setLoadNativeCollections(true);
        $this->objectAuditFactory->clearAuditCache();

        $revision = $this->getRevision(5);
        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
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
        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
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
        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertEquals('changed#7', $audited->getTitle());
        $this->assertCount(1, $audited->getOwned1());
        $this->assertCount(1, $audited->getOwned2());
        $o1 = $audited->getOwned1();
        $this->assertEquals('changed#7', $o1[0]->getTitle());
        $o2 = $audited->getOwned2();
        $this->assertEquals('owned21', $o2[0]->getTitle());

        $history = $this->auditManager->getObjectHistory(get_class($owner), $owner->getId());
        $this->assertContainsOnly(ChangedObject::class, $history);

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

        $this->em->persist($owner1);
        $this->em->persist($owner2);
        $this->em->persist($owned1);
        $this->em->persist($owned2);
        $this->em->persist($owned3);

        $this->em->flush(); //#1

        $owned1->setOwner($owner2);
        $this->em->flush(); //#2

        $this->em->remove($owned1);
        $this->em->flush(); //#3

        $owned2->setTitle('updated owned2');
        $this->em->flush(); //#4

        $this->em->remove($owned2);
        $this->em->flush(); //#5

        $this->em->remove($owned3);
        $this->em->flush(); //#6

        $revision = $this->getRevision(1);
        $owner = $this->auditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(3, $owner->getOwned1());

        $revision = $this->getRevision(2);
        $owner = $this->auditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(2, $owner->getOwned1());

        $revision = $this->getRevision(3);
        $owner = $this->auditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(2, $owner->getOwned1());

        $revision = $this->getRevision(4);
        $owner = $this->auditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(2, $owner->getOwned1());

        $revision = $this->getRevision(5);
        $owner = $this->auditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
        $this->assertCount(1, $owner->getOwned1());

        $revision = $this->getRevision(6);
        $owner = $this->auditManager->findObjectByRevision(get_class($owner1), $owner1->getId(), $revision);
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

        $this->em->persist($owner);
        $this->em->persist($owned);

        $this->em->flush(); //#1

        $ownerId1 = $owner->getId();
        $ownedId1 = $owned->getId();

        $owned->setTitle('associated#2');
        $owned->setOwner($owner);

        $this->em->flush(); //#2

        $owned->setTitle('deassociated#3');
        $owned->setOwner(null);

        $this->em->flush(); //#3

        $owned->setTitle('associated#4');
        $owned->setOwner($owner);

        $this->em->flush(); //#4

        $this->em->remove($owned);

        $this->em->flush(); //#5

        $owned = new OwnedEntity1();
        $owned->setTitle('recreated#6');
        $owned->setOwner($owner);

        $this->em->persist($owned);
        $this->em->flush(); //#6

        $ownedId2 = $owned->getId();

        $this->em->remove($owner);
        $this->em->flush(); //#7

        $revision = $this->getRevision(1);
        $auditedEntity = $this->auditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $this->assertEquals('created#1', $auditedEntity->getTitle());
        $this->assertCount(0, $auditedEntity->getOwned1());

        $revision = $this->getRevision(2);
        $auditedEntity = $this->auditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $o1 = $auditedEntity->getOwned1();
        $this->assertCount(1, $o1);
        $this->assertEquals($ownedId1, $o1[0]->getId());

        $revision = $this->getRevision(3);
        $auditedEntity = $this->auditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $this->assertCount(0, $auditedEntity->getOwned1());

        $revision = $this->getRevision(4);
        $auditedEntity = $this->auditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $this->assertCount(1, $auditedEntity->getOwned1());

        $revision = $this->getRevision(5);
        $auditedEntity = $this->auditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $this->assertCount(0, $auditedEntity->getOwned1());

        $revision = $this->getRevision(6);
        $auditedEntity = $this->auditManager->findObjectByRevision(get_class($owner), $ownerId1, $revision);
        $o1 = $auditedEntity->getOwned1();
        $this->assertCount(1, $o1);
        $this->assertEquals($ownedId2, $o1[0]->getId());

        $revision = $this->getRevision(7);
        $auditedEntity = $this->auditManager->findObjectByRevision(get_class($owned), $ownedId2, $revision);
        $this->assertEquals(null, $auditedEntity->getOwner());
    }

    public function testOneXRelations()
    {
        $owner = new OwnerEntity();
        $owner->setTitle('owner');

        $owned = new OwnedEntity1();
        $owned->setTitle('owned');
        $owned->setOwner($owner);

        $this->em->persist($owner);
        $this->em->persist($owned);

        $this->em->flush();
        //first revision done

        $owner->setTitle('changed#2');
        $owned->setTitle('changed#2');
        $this->em->flush();

        //checking first revision
        $revision = $this->getRevision(1);
        $audited = $this->auditManager->findObjectByRevision(get_class($owned), $owner->getId(), $revision);
        $this->assertEquals('owned', $audited->getTitle());
        $this->assertEquals('owner', $audited->getOwner()->getTitle());

        //checking second revision
        $revision = $this->getRevision(2);
        $audited = $this->auditManager->findObjectByRevision(get_class($owned), $owner->getId(), $revision);

        $this->assertEquals('changed#2', $audited->getTitle());
        $this->assertEquals('changed#2', $audited->getOwner()->getTitle());
    }

    public function testOneToManyJoinedInheritance()
    {
        $food = new FoodCategory();
        $this->em->persist($food);

        $parmesanCheese = new CheeseProduct('Parmesan');
        $this->em->persist($parmesanCheese);

        $cheddarCheese = new CheeseProduct('Cheddar');
        $this->em->persist($cheddarCheese);

        $vine = new WineProduct('Champagne');
        $this->em->persist($vine);

        $food->addProduct($parmesanCheese);
        $food->addProduct($cheddarCheese);
        $food->addProduct($vine);

        $this->em->flush();

        $auditedFood = $this->auditManager->findObjectByRevision(
            get_class($food),
            $food->getId(),
            $this->auditManager->getCurrentObjectRevision(get_class($food), $food->getId())
        );

        $this->assertInstanceOf(get_class($food), $auditedFood);
        $this->assertCount(3, $auditedFood->getProducts());

        list($productOne, $productTwo, $productThree) = $auditedFood->getProducts()->toArray();

        $this->assertInstanceOf(get_class($parmesanCheese), $productOne);
        $this->assertInstanceOf(get_class($cheddarCheese), $productTwo);
        $this->assertInstanceOf(get_class($vine), $productThree);

        $this->assertEquals($parmesanCheese->getId(), $productOne->getId());
        $this->assertEquals($cheddarCheese->getId(), $productTwo->getId());
    }

    public function testOneToManyWithIndexBy()
    {
        $page = new Page();
        $this->em->persist($page);

        $gbLocalization = new PageLocalization('en-GB');
        $this->em->persist($gbLocalization);

        $usLocalization = new PageLocalization('en-US');
        $this->em->persist($usLocalization);

        $page->addLocalization($gbLocalization);
        $page->addLocalization($usLocalization);

        $this->em->flush();

        $auditedPage = $this->auditManager->findObjectByRevision(
            get_class($page),
            $page->getId(),
            $this->auditManager->getCurrentObjectRevision(get_class($page), $page->getId())
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
        $this->em->persist($owner);

        $ownedOne = new OwnedEntity1();
        $ownedOne->setTitle('Owned#1');
        $ownedOne->setOwner($owner);
        $this->em->persist($ownedOne);

        $ownedTwo = new OwnedEntity1();
        $ownedTwo->setTitle('Owned#2');
        $ownedTwo->setOwner($owner);
        $this->em->persist($ownedTwo);

        $ownedThree = new OwnedEntity1();
        $ownedThree->setTitle('Owned#3');
        $ownedThree->setOwner($owner);
        $this->em->persist($ownedThree);

        $ownedFour = new OwnedEntity1();
        $ownedFour->setTitle('Owned#4');
        $ownedFour->setOwner($owner);
        $this->em->persist($ownedFour);

        $owner->addOwned1($ownedOne);
        $owner->addOwned1($ownedTwo);
        $owner->addOwned1($ownedThree);
        $owner->addOwned1($ownedFour);

        $owner->setTitle('Owner with four owned elements.');
        $this->em->flush(); //#1

        $owner->setTitle('Owner with three owned elements.');
        $this->em->remove($ownedTwo);

        $this->em->flush(); //#2

        $owner->setTitle('Just another revision.');

        $this->em->flush(); //#3

        $auditedOwner = $this->auditManager->findObjectByRevision(
            get_class($owner),
            $owner->getId(),
            $this->auditManager->getCurrentObjectRevision(get_class($owner), $owner->getId())
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

        $this->em->persist($base);
        $this->em->persist($referenced);

        $this->em->flush();

        $revision = $this->getRevision(1);
        $auditedBase = $this->auditManager->findObjectByRevision(get_class($base), $base->getId(), $revision);

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
        $this->em->persist($page);
        $this->em->flush();

        $pageAlias = new PageAlias($page, 'This is the alias');
        $this->em->persist($pageAlias);
        $this->em->flush();
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

        $this->em->persist($container1);
        $this->em->persist($container2);
        $this->em->persist($container3);
        $this->em->flush();

        $revision = $this->getRevision(1);
        $legal2Base = $this->auditManager->findObjectByRevision(get_class($legal2), $legal2->getId(), $revision);

        $this->assertEquals('container3', $legal2Base->getDataContainer()->getName());
    }

    public function testDiff()
    {
        $owner1 = new OwnerEntity();
        $owner1->setTitle('Owner 1');
        $owner2 = new OwnerEntity();
        $owner2->setTitle('Owner 2');
        $this->em->persist($owner1);
        $this->em->persist($owner2);

        $owned = new OwnedEntity1();
        $owned->setTitle('Owned');
        $this->em->persist($owned);
        $owned->setOwner($owner1);
        $this->em->flush();

        $owned->setOwner($owner2);
        $this->em->persist($owned);
        $this->em->flush();

        $revision1 = $this->getRevision(1);
        $revision2 = $this->getRevision(2);

        $diff = $this->auditManager->diffObjectRevisions(get_class($owned), 1, $revision1, $revision2);

        $this->assertSame($owner1->getTitle(), $diff['owner']['old']->getTitle());
        $this->assertSame($owner2->getTitle(), $diff['owner']['new']->getTitle());
        $this->assertEmpty($diff['owner']['same']);
    }

    public function testDoubleFieldDefinitionEdgeCase()
    {
        $owner = new ChildEntity();
        $owned = new RelatedEntity();

        $this->em->persist($owner);
        $this->em->persist($owned);
        $this->em->flush();

        $revision = $this->getRevision(1);
        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertInstanceOf(get_class($owner), $audited);

        $owner->setRelation($owned);
        $this->em->flush();

        $revision = $this->getRevision(2);
        $audited = $this->auditManager->findObjectByRevision(get_class($owner), $owner->getId(), $revision);
        $this->assertInstanceOf(get_class($owner), $audited);
    }
}
