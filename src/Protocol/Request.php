<?php


namespace EasySwoole\Rpc\Protocol;


use EasySwoole\Spl\SplBean;

class Request extends SplBean
{
    /** @var string|null */
    protected $service;
    /** @var string|null */
    protected $module;
    /** @var string|null */
    protected $action;
    /** @var mixed */
    protected $arg;
    /** @var string */
    protected $requestUUID;

    /**
     * @return string|null
     */
    public function getService(): ?string
    {
        return $this->service;
    }

    /**
     * @param string|null $service
     */
    public function setService(?string $service): void
    {
        $this->service = $service;
    }

    /**
     * @return string|null
     */
    public function getModule(): ?string
    {
        return $this->module;
    }

    /**
     * @param string|null $module
     */
    public function setModule(?string $module): void
    {
        $this->module = $module;
    }

    /**
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @param string|null $action
     */
    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    /**
     * @return mixed
     */
    public function getArg()
    {
        return $this->arg;
    }

    /**
     * @param mixed $arg
     */
    public function setArg($arg): void
    {
        $this->arg = $arg;
    }

    /**
     * @return string
     */
    public function getRequestUUID(): string
    {
        return $this->requestUUID;
    }

    /**
     * @param string $requestUUID
     */
    public function setRequestUUID(string $requestUUID): void
    {
        $this->requestUUID = $requestUUID;
    }
}