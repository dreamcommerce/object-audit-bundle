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

use DateTime;
use Doctrine\Common\Collections\Collection;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Core\PetAudit;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\RevisionTest;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotFoundException;
use DreamCommerce\Component\ObjectAudit\Model\ChangedObject;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Core\AnimalAudit;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Core\ArticleAudit;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Core\Cat;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Core\Dog;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Core\Fox;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Core\ProfileAudit;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Core\Rabbit;
use DreamCommerce\Tests\ObjectAuditBundle\Fixtures\Core\UserAudit;

class CoreTest extends BaseTest
{
    protected $schemaEntities = array(
        RevisionTest::class,
        ArticleAudit::class,
        UserAudit::class,
        ProfileAudit::class,
        AnimalAudit::class,
        Fox::class,
        Rabbit::class,
        PetAudit::class,
        Cat::class,
        Dog::class
    );

    protected $auditedEntities = array(
        ArticleAudit::class,
        UserAudit::class,
        ProfileAudit::class,
        AnimalAudit::class,
        Rabbit::class,
        Fox::class,
        Cat::class,
        Dog::class
    );

    public function testAuditable()
    {
        $user = new UserAudit("beberlei");
        $article = new ArticleAudit("test", "yadda!", $user, 'text');
        $rabbit = new Rabbit('rabbit', 'white');
        $foxy = new Fox('foxy', 60);
        $doggy = new Dog('woof', 80);
        $cat = new Cat('pusheen', '#b5a89f');

        $this->em->persist($user);
        $this->em->persist($article);
        $this->em->persist($rabbit);
        $this->em->persist($foxy);
        $this->em->persist($doggy);
        $this->em->persist($cat);
        $this->em->flush();

        $this->assertEquals(1, count($this->em->getConnection()->fetchAll('SELECT id FROM revisions')));
        $this->assertEquals(1, count($this->em->getConnection()->fetchAll('SELECT * FROM UserAudit_audit')));
        $this->assertEquals(1, count($this->em->getConnection()->fetchAll('SELECT * FROM ArticleAudit_audit')));
        $this->assertEquals(2, count($this->em->getConnection()->fetchAll('SELECT * FROM AnimalAudit_audit')));

        $article->setText("oeruoa");
        $rabbit->setName('Rabbit');
        $rabbit->setColor('gray');
        $foxy->setName('Foxy');
        $foxy->setTailLength(55);

        $this->em->flush();

        $this->assertEquals(2, count($this->em->getConnection()->fetchAll('SELECT id FROM revisions')));
        $this->assertEquals(2, count($this->em->getConnection()->fetchAll('SELECT * FROM ArticleAudit_audit')));
        $this->assertEquals(4, count($this->em->getConnection()->fetchAll('SELECT * FROM AnimalAudit_audit')));

        $this->em->remove($user);
        $this->em->remove($article);
        $this->em->remove($rabbit);
        $this->em->remove($foxy);
        $this->em->flush();

        $this->assertEquals(3, count($this->em->getConnection()->fetchAll('SELECT id FROM revisions')));
        $this->assertEquals(2, count($this->em->getConnection()->fetchAll('SELECT * FROM UserAudit_audit')));
        $this->assertEquals(3, count($this->em->getConnection()->fetchAll('SELECT * FROM ArticleAudit_audit')));
        $this->assertEquals(6, count($this->em->getConnection()->fetchAll('SELECT * FROM AnimalAudit_audit')));
    }

    public function testFind()
    {
        $user = new UserAudit("beberlei");
        $foxy = new Fox('foxy', 55);
        $cat = new Cat('pusheen', '#b5a89f');

        $this->em->persist($cat);
        $this->em->persist($user);
        $this->em->persist($foxy);
        $this->em->flush();

        /** @var RevisionInterface $revision */
        $revision = $this->auditManager->getRevisionRepository()->find(1);
        $this->assertInstanceOf(RevisionInterface::class, $revision);

        $auditUser = $this->auditManager->findObjectByRevision(get_class($user), $user->getId(), $revision);

        $this->assertInstanceOf(get_class($user), $auditUser, "Audited User is also a User instance.");
        $this->assertEquals($user->getId(), $auditUser->getId(), "Ids of audited user and real user should be the same.");
        $this->assertEquals($user->getName(), $auditUser->getName(), "Name of audited user and real user should be the same.");
        $this->assertFalse($this->em->contains($auditUser), "Audited User should not be in the identity map.");
        $this->assertNotSame($user, $auditUser, "User and Audited User instances are not the same.");

        $auditFox = $this->auditManager->findObjectByRevision(get_class($foxy), $foxy->getId(), $revision);

        $this->assertInstanceOf(get_class($foxy), $auditFox, "Audited SINGLE_TABLE class keeps it's class.");
        $this->assertEquals($foxy->getId(), $auditFox->getId(), "Ids of audited SINGLE_TABLE class and real SINGLE_TABLE class should be the same.");
        $this->assertEquals($foxy->getName(), $auditFox->getName(), "Loaded and original attributes should be the same for SINGLE_TABLE inheritance.");
        $this->assertEquals($foxy->getTailLength(), $auditFox->getTailLength(), "Loaded and original attributes should be the same for SINGLE_TABLE inheritance.");
        $this->assertFalse($this->em->contains($auditFox), "Audited SINGLE_TABLE inheritance class should not be in the identity map.");
        $this->assertNotSame($this, $auditFox, "Audited and new entities should not be the same object for SINGLE_TABLE inheritance.");

        $auditCat = $this->auditManager->findObjectByRevision(get_class($cat), $cat->getId(), $revision);

        $this->assertInstanceOf(get_class($cat), $auditCat, "Audited JOINED class keeps it's class.");
        $this->assertEquals($cat->getId(), $auditCat->getId(), "Ids of audited JOINED class and real JOINED class should be the same.");
        $this->assertEquals($cat->getName(), $auditCat->getName(), "Loaded and original attributes should be the same for JOINED inheritance.");
        $this->assertEquals($cat->getColor(), $auditCat->getColor(), "Loaded and original attributes should be the same for JOINED inheritance.");
        $this->assertFalse($this->em->contains($auditCat), "Audited JOINED inheritance class should not be in the identity map.");
        $this->assertNotSame($this, $auditCat, "Audited and new entities should not be the same object for JOINED inheritance.");
    }

    public function testFindNoRevisionFound()
    {
        $this->expectException(ObjectNotFoundException::class);
        $this->expectExceptionCode(ObjectNotFoundException::CODE_OBJECT_NOT_EXIST_AT_SPECIFIC_REVISION);

        $revision = new RevisionTest();
        $this->em->persist($revision);
        $this->em->flush();

        $this->auditManager->findObjectByRevision(UserAudit::class, 1, $revision);
    }

    public function testFindNotAudited()
    {
        $this->setExpectedException(
            'DreamCommerce\ObjectAudit\Exception\NotAuditedException',
            "Class 'stdClass' is not audited."
        );

        $this->expectException(ObjectNotAuditedException::class);
        $this->expectExceptionCode(ObjectNotAuditedException::CODE_OBJECT_IS_NOT_AUDITED);

        $revision = new RevisionTest();
        $this->em->persist($revision);
        $this->em->flush();

        $this->auditManager->findObjectByRevision("stdClass", 1, $revision);
    }

    public function testFindRevisionHistory()
    {
        $user = new UserAudit("beberlei");

        $this->em->persist($user);
        $this->em->flush();

        $article = new ArticleAudit("test", "yadda!", $user, 'text');

        $this->em->persist($article);
        $this->em->flush();

        /** @var RevisionInterface[]|Collection $revisions */
        $revisions = $this->auditManager->getRevisionRepository()->findAll();

        $this->assertEquals(2, count($revisions));
        $this->assertContainsOnly(RevisionInterface::class, $revisions);

        $this->assertEquals(1, $revisions[0]->getId());
        $this->assertInstanceOf(DateTime::class, $revisions[0]->getCreatedAt());

        $this->assertEquals(2, $revisions[1]->getId());
        $this->assertInstanceOf(DateTime::class, $revisions[1]->getCreatedAt());
    }

    public function testFindEntitesChangedAtRevision()
    {
        $user = new UserAudit("beberlei");
        $article = new ArticleAudit("test", "yadda!", $user, 'text');
        $foxy = new Fox('foxy', 50);
        $rabbit = new Rabbit('rabbit', 'white');
        $cat = new Cat('pusheen', '#b5a89f');
        $dog = new Dog('doggy', 80);

        $this->em->persist($dog);
        $this->em->persist($cat);
        $this->em->persist($foxy);
        $this->em->persist($rabbit);
        $this->em->persist($user);
        $this->em->persist($article);
        $this->em->flush();

        $revision = $this->getRevision(1);
        /** @var ChangedObject[] $changedEntities */
        $changedEntities = $this->auditManager->findAllObjectsChangedAtRevision($revision);

        //duplicated entries means a bug with discriminators
        $this->assertEquals(6, count($changedEntities));

        usort($changedEntities, function(ChangedObject $a, ChangedObject $b) {
            return strcmp($a->getClassName(), $b->getClassName());
        });

        $this->assertContainsOnly(ChangedObject::class, $changedEntities);

        $this->assertEquals($revision, $changedEntities[0]->getRevision());
        $this->assertEquals(RevisionInterface::ACTION_INSERT, $changedEntities[0]->getRevisionType());
        $this->assertEquals(ArticleAudit::class, $changedEntities[0]->getClassName());
        $this->assertInstanceOf(ArticleAudit::class, $changedEntities[0]->getObject());
        $this->assertEquals(1, $changedEntities[0]->getObject()->getId());
        $this->assertEquals($this->em, $changedEntities[0]->getPersistManager());

        $this->assertEquals($revision, $changedEntities[1]->getRevision());
        $this->assertEquals(RevisionInterface::ACTION_INSERT, $changedEntities[1]->getRevisionType());
        $this->assertEquals(Cat::class, $changedEntities[1]->getClassName());
        $this->assertInstanceOf(Cat::class, $changedEntities[1]->getObject());
        $this->assertEquals(1, $changedEntities[1]->getObject()->getId());
        $this->assertEquals($this->em, $changedEntities[1]->getPersistManager());
    }

    public function testNotVersionedRelationFind()
    {
        // Insert user without the manager to skip revision registering.
        $this->em->getConnection()->insert(
            $this->em->getClassMetadata(UserAudit::class)->getTableName(),
            array(
                'id' => 1,
                'name' => 'beberlei',
            )
        );

        $user = $this->em->getRepository(UserAudit::class)->find(1);

        $article = new ArticleAudit(
            "test",
            "yadda!",
            $user,
            'text'
        );

        $this->em->persist($article);
        $this->em->flush();

        $revision = $this->getRevision(1);
        /** @var ArticleAudit $article */
        $article = $this->auditManager->findObjectByRevision(get_class($article), 1, $revision);

        $this->assertNotNull($article);
        $this->assertSame('beberlei', $article->getAuthor()->getName());
    }

    public function testNotVersionedReverseRelationFind()
    {
        $user = new UserAudit('beberlei');

        $this->em->persist($user);
        $this->em->flush();

        // Insert user without the manager to skip revision registering.
        $this->em->getConnection()->insert(
            $this->em->getClassMetadata(ProfileAudit::class)->getTableName(),
            array(
                'id' => 1,
                'biography' => 'He is an amazing contributor!',
                'user_id' => 1,
            )
        );

        $revision = $this->getRevision(1);
        /** @var UserAudit $user */
        $user = $this->auditManager->findObjectByRevision(get_class($user), 1, $revision);
        $this->assertNotNull($user);
        $profile = $user->getProfile();
        $this->assertNotNull($profile);
        $this->assertSame('He is an amazing contributor!', $profile->getBiography());
    }

    public function testFindRevisions()
    {
        $user = new UserAudit("beberlei");
        $foxy = new Fox('foxy', 30);
        $rabbit = new Rabbit('rabbit', 'white');
        $cat = new Cat('pusheen', '#b5a89f');
        $dog = new Dog('doggy', 80);

        $this->em->persist($dog);
        $this->em->persist($cat);
        $this->em->persist($user);
        $this->em->persist($foxy);
        $this->em->persist($rabbit);
        $this->em->flush();

        $foxy->setName('Foxy');
        $dog->setName('doge');
        $user->setName("beberlei2");
        $this->em->flush();

        /** @var RevisionInterface[] $revisions */
        $revisions = $this->auditManager->findObjectRevisions(get_class($user), $user->getId());

        $this->assertEquals(2, count($revisions));
        $this->assertContainsOnly(RevisionInterface::class, $revisions);

        $this->assertEquals(2, $revisions[0]->getId());
        $this->assertInstanceOf(DateTime::class, $revisions[0]->getCreatedAt());

        $this->assertEquals(1, $revisions[1]->getId());
        $this->assertInstanceOf(DateTime::class, $revisions[1]->getCreatedAt());

        //SINGLE_TABLE should have separate revision history
        $this->assertEquals(2, count($this->auditManager->findObjectRevisions(get_class($foxy), $foxy->getId())));
        $this->assertEquals(1, count($this->auditManager->findObjectRevisions(get_class($rabbit), $rabbit->getId())));
        //JOINED too
        $this->assertEquals(2, count($this->auditManager->findObjectRevisions(get_class($dog), $dog->getId())));
        $this->assertEquals(1, count($this->auditManager->findObjectRevisions(get_class($cat), $cat->getId())));
    }

    public function testFindCurrentRevision()
    {
        $user = new UserAudit('Broncha');

        $this->em->persist($user);
        $this->em->flush();

        $user->setName("Rajesh");
        $this->em->flush();

        $revision = $this->auditManager->getCurrentObjectRevision(get_class($user), $user->getId());
        $this->assertInstanceOf(RevisionInterface::class, $revision);
        $this->assertEquals(2, $revision->getId());

        $user->setName("David");
        $this->em->flush();

        $revision = $this->auditManager->getCurrentObjectRevision(get_class($user), $user->getId());
        $this->assertInstanceOf(RevisionInterface::class, $revision);
        $this->assertEquals(3, $revision->getId());
    }

    public function testGlobalIgnoreProperties()
    {
        $user = new UserAudit("welante");
        $article = new ArticleAudit("testcolumn", "yadda!", $user, 'text');

        $this->em->persist($user);
        $this->em->persist($article);
        $this->em->flush();

        $article->setText("testcolumn2");
        $this->em->persist($article);
        $this->em->flush();

        $revision = $this->auditManager->getCurrentObjectRevision(get_class($article), $article->getId());
        $this->assertInstanceOf(RevisionInterface::class, $revision);
        $this->assertEquals(2, $revision->getId());

        $article->setIgnoreMe("textnew");
        $this->em->persist($article);
        $this->em->flush();

        $revision = $this->auditManager->getCurrentObjectRevision(get_class($article), $article->getId());
        $this->assertInstanceOf(RevisionInterface::class, $revision);
        $this->assertEquals(2, $revision->getId());
    }

    public function testDeleteUnInitProxy()
    {
        $user = new UserAudit("beberlei");

        $this->em->persist($user);
        $this->em->flush();

        unset($user);
        $this->em->clear();

        $user = $this->em->getReference(UserAudit::class, 1);
        $this->em->remove($user);
        $this->em->flush();

        $revision = $this->getRevision(2);
        /** @var ChangedObject[] $changedEntities */
        $changedEntities = $this->auditManager->findAllObjectsChangedAtRevision($revision);

        $this->assertEquals(1, count($changedEntities));
        $this->assertContainsOnly(ChangedObject::class, $changedEntities);
        $object = $changedEntities[0]->getObject();
        $this->assertNotNull($object);
        $this->assertEquals(UserAudit::class, get_class($object));
        $this->assertEquals(1, $object->getId());
        $this->assertEquals(RevisionInterface::ACTION_DELETE, $changedEntities[0]->getRevisionType());
    }
}
