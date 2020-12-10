<?php


namespace EasySwoole\Rpc\Config;


class Client
{
    protected $maxPackageSize = 1024 * 1024 * 2;//2M
    /** @var null|callable $onGlobalSuccess */
    protected $onGlobalSuccess;
    /** @var null|callable $onGlobalFail */
    protected $onGlobalFail;

    /**
     * @return int
     */
    public function getMaxPackageSize()
    {
        return $this->maxPackageSize;
    }

    /**
     * @param int $maxPackageSize
     * @return $this
     */
    public function setMaxPackageSize(int $maxPackageSize): self
    {
        $this->maxPackageSize = $maxPackageSize;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getOnGlobalSuccess(): ?callable
    {
        return $this->onGlobalSuccess;
    }

    /**
     * @param callable|null $onGlobalSuccess
     * @return $this
     */
    public function setOnGlobalSuccess(?callable $onGlobalSuccess): self
    {
        $this->onGlobalSuccess = $onGlobalSuccess;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getOnGlobalFail(): ?callable
    {
        return $this->onGlobalFail;
    }

    /**
     * @param callable|null $onGlobalFail
     * @return $this
     */
    public function setOnGlobalFail(?callable $onGlobalFail): self
    {
        $this->onGlobalFail = $onGlobalFail;
        return $this;
    }
}