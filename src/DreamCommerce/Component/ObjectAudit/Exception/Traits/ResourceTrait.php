<?php

namespace DreamCommerce\Component\ObjectAudit\Exception\Traits;

trait ResourceTrait
{
    use ObjectTrait;

    /**
     * @var string|null
     */
    protected $resourceName;

    /**
     * @return string|null
     */
    public function getResourceName()
    {
        return $this->resourceName;
    }

    /**
     * @param string $resourceName
     *
     * @return $this
     */
    public function setResourceName(string $resourceName)
    {
        $this->resourceName = $resourceName;

        return $this;
    }
}
