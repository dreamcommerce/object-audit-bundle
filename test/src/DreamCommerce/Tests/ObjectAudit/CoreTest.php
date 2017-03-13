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
use Doctrine\Common\Collections\Collection;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectAuditNotFoundException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Model\ObjectAudit;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Fixtures\ObjectAudit\Entity\Common\RevisionTest;
use DreamCommerce\Fixtures\ObjectAudit\Entity\Core\ArticleAudit;
use DreamCommerce\Fixtures\ObjectAudit\Entity\Core\Cat;
use DreamCommerce\Fixtures\ObjectAudit\Entity\Core\Dog;
use DreamCommerce\Fixtures\ObjectAudit\Entity\Core\Fox;
use DreamCommerce\Fixtures\ObjectAudit\Entity\Core\ProfileAudit;
use DreamCommerce\Fixtures\ObjectAudit\Entity\Core\Rabbit;
use DreamCommerce\Fixtures\ObjectAudit\Entity\Core\UserAudit;

class CoreTest extends BaseTest
{
    protected $fixturesPath = __DIR__ . '/../../Fixtures/ObjectAudit/Entity/Core';

    public function testAuditable()
    {
        $user = new UserAudit("beberlei");
        $article = new ArticleAudit("test", "yadda!", $user, 'globalIgnoredText', 'localIgnoredText');
        $rabbit = new Rabbit('rabbit', 'white');
        $foxy = new Fox('foxy', 60);
        $doggy = new Dog('woof', 80);
        $cat = new Cat('pusheen', '#b5a89f');

        $this->persistManager->persist($user);
        $this->persistManager->persist($article);
        $this->persistManager->persist($rabbit);
        $this->persistManager->persist($foxy);
        $this->persistManager->persist($doggy);
        $this->persistManager->persist($cat);
        $this->persistManager->flush();

        $this->assertEquals(1, count($this->auditPersistManager->getConnection()->fetchAll('SELECT id FROM revisions')));
        $this->assertEquals(1, count($this->auditPersistManager->getConnection()->fetchAll('SELECT * FROM UserAudit_audit')));
        $this->assertEquals(1, count($this->auditPersistManager->getConnection()->fetchAll('SELECT * FROM ArticleAudit_audit')));
        $this->assertEquals(2, count($this->auditPersistManager->getConnection()->fetchAll('SELECT * FROM AnimalAudit_audit')));

        $article->setText("oeruoa");
        $rabbit->setName('Rabbit');
        $rabbit->setColor('gray');
        $foxy->setName('Foxy');
        $foxy->setTailLength(55);

        $this->persistManager->flush();

        $this->assertEquals(2, count($this->auditPersistManager->getConnection()->fetchAll('SELECT id FROM revisions')));
        $this->assertEquals(2, count($this->auditPersistManager->getConnection()->fetchAll('SELECT * FROM ArticleAudit_audit')));
        $this->assertEquals(4, count($this->auditPersistManager->getConnection()->fetchAll('SELECT * FROM AnimalAudit_audit')));

        $this->persistManager->remove($user);
        $this->persistManager->remove($article);
        $this->persistManager->remove($rabbit);
        $this->persistManager->remove($foxy);
        $this->persistManager->flush();

        $this->assertEquals(3, count($this->auditPersistManager->getConnection()->fetchAll('SELECT id FROM revisions')));
        $this->assertEquals(2, count($this->auditPersistManager->getConnection()->fetchAll('SELECT * FROM UserAudit_audit')));
        $this->assertEquals(3, count($this->auditPersistManager->getConnection()->fetchAll('SELECT * FROM ArticleAudit_audit')));
        $this->assertEquals(6, count($this->auditPersistManager->getConnection()->fetchAll('SELECT * FROM AnimalAudit_audit')));
    }

    public function testFind()
    {
        $user = new UserAudit("beberlei");
        $foxy = new Fox('foxy', 55);
        $cat = new Cat('pusheen', '#b5a89f');

        $this->persistManager->persist($cat);
        $this->persistManager->persist($user);
        $this->persistManager->persist($foxy);
        $this->persistManager->flush();

        /** @var RevisionInterface $revision */
        $revision = $this->revisionManager->getRepository()->find(1);
        $this->assertInstanceOf(RevisionInterface::class, $revision);

        $auditUser = $this->objectAuditManager->find(get_class($user), $user->getId(), $revision);

        $this->assertInstanceOf(get_class($user), $auditUser, "Audited User is also a User instance.");
        $this->assertEquals($user->getId(), $auditUser->getId(), "Ids of audited user and real user should be the same.");
        $this->assertEquals($user->getName(), $auditUser->getName(), "Name of audited user and real user should be the same.");
        $this->assertFalse($this->persistManager->contains($auditUser), "Audited User should not be in the identity map.");
        $this->assertNotSame($user, $auditUser, "User and Audited User instances are not the same.");

        $auditFox = $this->objectAuditManager->find(get_class($foxy), $foxy->getId(), $revision);

        $this->assertInstanceOf(get_class($foxy), $auditFox, "Audited SINGLE_TABLE class keeps it's class.");
        $this->assertEquals($foxy->getId(), $auditFox->getId(), "Ids of audited SINGLE_TABLE class and real SINGLE_TABLE class should be the same.");
        $this->assertEquals($foxy->getName(), $auditFox->getName(), "Loaded and original attributes should be the same for SINGLE_TABLE inheritance.");
        $this->assertEquals($foxy->getTailLength(), $auditFox->getTailLength(), "Loaded and original attributes should be the same for SINGLE_TABLE inheritance.");
        $this->assertFalse($this->persistManager->contains($auditFox), "Audited SINGLE_TABLE inheritance class should not be in the identity map.");
        $this->assertNotSame($this, $auditFox, "Audited and new entities should not be the same object for SINGLE_TABLE inheritance.");

        $auditCat = $this->objectAuditManager->find(get_class($cat), $cat->getId(), $revision);

        $this->assertInstanceOf(get_class($cat), $auditCat, "Audited JOINED class keeps it's class.");
        $this->assertEquals($cat->getId(), $auditCat->getId(), "Ids of audited JOINED class and real JOINED class should be the same.");
        $this->assertEquals($cat->getName(), $auditCat->getName(), "Loaded and original attributes should be the same for JOINED inheritance.");
        $this->assertEquals($cat->getColor(), $auditCat->getColor(), "Loaded and original attributes should be the same for JOINED inheritance.");
        $this->assertFalse($this->persistManager->contains($auditCat), "Audited JOINED inheritance class should not be in the identity map.");
        $this->assertNotSame($this, $auditCat, "Audited and new entities should not be the same object for JOINED inheritance.");
    }

    public function testFindNoRevisionFound()
    {
        $this->expectException(ObjectAuditNotFoundException::class);
        $this->expectExceptionCode(ObjectAuditNotFoundException::CODE_OBJECT_AUDIT_NOT_EXIST_AT_SPECIFIC_REVISION);

        $revision = new RevisionTest();
        $this->auditPersistManager->persist($revision);
        $this->auditPersistManager->flush();

        $this->objectAuditManager->find(UserAudit::class, 1, $revision);
    }

    public function testFindNotAudited()
    {
        $this->setExpectedException(
            'DreamCommerce\ObjectAudit\Exception\NotAuditedException',
            "Class is not audited"
        );

        $this->expectException(ObjectNotAuditedException::class);
        $this->expectExceptionCode(ObjectNotAuditedException::CODE_CLASS_IS_NOT_AUDITED);

        $revision = new RevisionTest();
        $this->auditPersistManager->persist($revision);
        $this->auditPersistManager->flush();

        $this->objectAuditManager->find("stdClass", 1, $revision);
    }

    public function testFindRevisionHistory()
    {
        $user = new UserAudit("beberlei");

        $this->persistManager->persist($user);
        $this->persistManager->flush();

        $article = new ArticleAudit("test", "yadda!", $user, 'globalIgnoredText', 'localIgnoredText');

        $this->persistManager->persist($article);
        $this->persistManager->flush();

        /** @var RevisionInterface[]|Collection $revisions */
        $revisions = $this->revisionManager->getRepository()->findAll();

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
        $article = new ArticleAudit("test", "yadda!", $user, 'globalIgnoredText', 'localIgnoredText');
        $foxy = new Fox('foxy', 50);
        $rabbit = new Rabbit('rabbit', 'white');
        $cat = new Cat('pusheen', '#b5a89f');
        $dog = new Dog('doggy', 80);

        $this->persistManager->persist($dog);
        $this->persistManager->persist($cat);
        $this->persistManager->persist($foxy);
        $this->persistManager->persist($rabbit);
        $this->persistManager->persist($user);
        $this->persistManager->persist($article);
        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        /** @var ObjectAudit[] $objects */
        $objects = $this->objectAuditManager->findAllChangesAtRevision($revision);

        //duplicated entries means a bug with discriminators
        $this->assertEquals(6, count($objects));

        usort($objects, function (ObjectAudit $a, ObjectAudit $b) {
            return strcmp($a->getClassName(), $b->getClassName());
        });

        $this->assertContainsOnly(ObjectAudit::class, $objects);

        $this->assertEquals($revision, $objects[0]->getRevision());
        $this->assertEquals(RevisionInterface::ACTION_INSERT, $objects[0]->getType());
        $this->assertEquals(ArticleAudit::class, $objects[0]->getClassName());
        $this->assertInstanceOf(ArticleAudit::class, $objects[0]->getObject());
        $this->assertEquals($article->getId(), $objects[0]->getObject()->getId());
        $this->assertEquals($this->persistManager, $objects[0]->getPersistManager());

        $this->assertEquals($revision, $objects[1]->getRevision());
        $this->assertEquals(RevisionInterface::ACTION_INSERT, $objects[1]->getType());
        $this->assertEquals(Cat::class, $objects[1]->getClassName());
        $this->assertInstanceOf(Cat::class, $objects[1]->getObject());
        $this->assertEquals($cat->getId(), $objects[1]->getObject()->getId());
        $this->assertEquals($this->persistManager, $objects[1]->getPersistManager());
    }

    public function testNotVersionedRelationFind()
    {
        // Insert user without the manager to skip revision registering.
        $this->persistManager->getConnection()->insert(
            $this->persistManager->getClassMetadata(UserAudit::class)->getTableName(),
            array(
                'id' => 1,
                'name' => 'beberlei',
            )
        );

        $user = $this->persistManager->getRepository(UserAudit::class)->find(1);

        $article = new ArticleAudit(
            "test",
            "yadda!",
            $user,
            'globalIgnoredText',
            'localIgnoredText'
        );

        $this->persistManager->persist($article);
        $this->persistManager->flush();

        $revision = $this->getRevision(1);
        /** @var ArticleAudit $article */
        $article = $this->objectAuditManager->find(get_class($article), 1, $revision);

        $this->assertNotNull($article);
        $this->assertSame('beberlei', $article->getAuthor()->getName());
    }

    public function testNotVersionedReverseRelationFind()
    {
        $user = new UserAudit('beberlei');

        $this->persistManager->persist($user);
        $this->persistManager->flush();

        // Insert user without the manager to skip revision registering.
        $this->persistManager->getConnection()->insert(
            $this->persistManager->getClassMetadata(ProfileAudit::class)->getTableName(),
            array(
                'id' => 1,
                'biography' => 'He is an amazing contributor!',
                'user_id' => 1,
            )
        );

        $revision = $this->getRevision(1);
        /** @var UserAudit $user */
        $user = $this->objectAuditManager->find(get_class($user), 1, $revision);
        $this->assertNotNull($user);
        $profile = $user->getProfile();
        $this->assertNotNull($profile);
        $this->assertSame('He is an amazing contributor!', $profile->getBiography());
    }

    public function testGetRevisions()
    {
        $user = new UserAudit("beberlei");
        $foxy = new Fox('foxy', 30);
        $rabbit = new Rabbit('rabbit', 'white');
        $cat = new Cat('pusheen', '#b5a89f');
        $dog = new Dog('doggy', 80);

        $this->persistManager->persist($dog);
        $this->persistManager->persist($cat);
        $this->persistManager->persist($user);
        $this->persistManager->persist($foxy);
        $this->persistManager->persist($rabbit);
        $this->persistManager->flush();

        $foxy->setName('Foxy');
        $dog->setName('doge');
        $user->setName("beberlei2");
        $this->persistManager->flush();

        /** @var RevisionInterface[] $revisions */
        $revisions = $this->objectAuditManager->getRevisions(get_class($user), $user->getId());

        $this->assertEquals(2, count($revisions));
        $this->assertContainsOnly(RevisionInterface::class, $revisions);

        $this->assertEquals(2, $revisions[0]->getId());
        $this->assertInstanceOf(DateTime::class, $revisions[0]->getCreatedAt());

        $this->assertEquals(1, $revisions[1]->getId());
        $this->assertInstanceOf(DateTime::class, $revisions[1]->getCreatedAt());

        //SINGLE_TABLE should have separate revision history
        $this->assertEquals(2, count($this->objectAuditManager->getRevisions(get_class($foxy), $foxy->getId())));
        $this->assertEquals(1, count($this->objectAuditManager->getRevisions(get_class($rabbit), $rabbit->getId())));
        //JOINED too
        $this->assertEquals(2, count($this->objectAuditManager->getRevisions(get_class($dog), $dog->getId())));
        $this->assertEquals(1, count($this->objectAuditManager->getRevisions(get_class($cat), $cat->getId())));
    }

    public function testFindCurrentRevision()
    {
        $user = new UserAudit('Broncha');

        $this->persistManager->persist($user);
        $this->persistManager->flush();

        $user->setName("Rajesh");
        $this->persistManager->flush();

        $revision = $this->objectAuditManager->getRevision(get_class($user), $user->getId());
        $this->assertInstanceOf(RevisionInterface::class, $revision);
        $this->assertEquals(2, $revision->getId());

        $user->setName("David");
        $this->persistManager->flush();

        $revision = $this->objectAuditManager->getRevision(get_class($user), $user->getId());
        $this->assertInstanceOf(RevisionInterface::class, $revision);
        $this->assertEquals(3, $revision->getId());
    }

    public function testIgnoreProperties()
    {
        $user = new UserAudit("welante");
        $article = new ArticleAudit("testcolumn", "yadda!", $user, 'globalIgnoredText', 'localIgnoredText');

        $this->persistManager->persist($user);
        $this->persistManager->persist($article);
        $this->persistManager->flush();

        $article->setText("testcolumn2");
        $this->persistManager->persist($article);
        $this->persistManager->flush();

        $revision = $this->objectAuditManager->getRevision(get_class($article), $article->getId());
        $this->assertInstanceOf(RevisionInterface::class, $revision);
        $this->assertEquals(2, $revision->getId());

        $article->setGlobalIgnoreMe("newGlobalIgnoredText");
        $this->persistManager->persist($article);
        $this->persistManager->flush();

        $revision = $this->objectAuditManager->getRevision(get_class($article), $article->getId());
        $this->assertInstanceOf(RevisionInterface::class, $revision);
        $this->assertEquals(2, $revision->getId());

        $article->setLocalIgnoreMe("newLocalIgnoredText");
        $this->persistManager->persist($article);
        $this->persistManager->flush();

        $revision = $this->objectAuditManager->getRevision(get_class($article), $article->getId());
        $this->assertInstanceOf(RevisionInterface::class, $revision);
        $this->assertEquals(2, $revision->getId());
    }

    public function testDeleteUnInitProxy()
    {
        $user = new UserAudit("beberlei");

        $this->persistManager->persist($user);
        $this->persistManager->flush();

        unset($user);
        $this->persistManager->clear();

        $user = $this->persistManager->getReference(UserAudit::class, 1);
        $this->persistManager->remove($user);
        $this->persistManager->flush();

        $revision = $this->getRevision(2);
        /** @var ObjectAudit[] $objects */
        $objects = $this->objectAuditManager->findAllChangesAtRevision($revision);

        $this->assertEquals(1, count($objects));
        $this->assertContainsOnly(ObjectAudit::class, $objects);
        $object = $objects[0]->getObject();
        $this->assertNotNull($object);
        $this->assertEquals(UserAudit::class, get_class($object));
        $this->assertEquals(1, $object->getId());
        $this->assertEquals(RevisionInterface::ACTION_DELETE, $objects[0]->getType());
    }
}
