<?php

namespace DreamCommerce\Component\ObjectAudit\Exception;

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;

class ObjectException extends AuditException
{
    /**
     * @var string
     */
    protected $className;

    /**
     * @var mixed
     */
    protected $id;

    /**
     * @var RevisionInterface
     */
    protected $revision;

    /**
     * @return string|null
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @return mixed|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $className
     *
     * @return $this
     */
    public function setClassName(string $className)
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @param mixed $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return RevisionInterface|null
     */
    public function getRevision()
    {
        return $this->revision;
    }

    /**
     * @param RevisionInterface $revision
     *
     * @return $this
     */
    public function setRevision(RevisionInterface $revision)
    {
        $this->revision = $revision;

        return $this;
    }
}
