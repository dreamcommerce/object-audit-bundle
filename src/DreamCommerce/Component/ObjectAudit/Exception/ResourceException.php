<?php

namespace DreamCommerce\Component\ObjectAudit\Exception;

class ResourceException extends ObjectException
{
    /**
     * @var string
     */
    protected $resourceName;

    /**
     * @return string
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
