<?php

namespace DreamCommerce\Tests\ObjectAudit\Fixtures\Common;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

/**
 * @ORM\Entity(repositoryClass="\DreamCommerce\Component\ObjectAudit\Repository\ORMRevisionRepository")
 * @ORM\Table(name="revisions")
 */
class RevisionTest implements RevisionInterface
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue **/
    private $id;

    /** @ORM\Column(type="datetime") **/
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}