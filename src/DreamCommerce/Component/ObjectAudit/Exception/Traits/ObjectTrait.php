<?php

namespace DreamCommerce\Component\ObjectAudit\Exception\Traits;

trait ObjectTrait
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
}
