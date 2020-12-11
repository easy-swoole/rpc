<?php


namespace EasySwoole\Rpc\NetWork;


class Request
{
    /** @var string */
    private $service;
    /** @var string */
    private $module;
    /** @var string */
    private $action;
    /** @var mixed */
    private $arg;

    function __construct(string $service,string $module,string $action, $arg = null)
    {
        $this->service = $service;
        $this->module = $module;
        $this->action = $action;
        $this->arg = $arg;
    }

    /**
     * @return string
     */
    public function getService(): ?string
    {
        return $this->service;
    }

    /**
     * @return string
     */
    public function getModule(): ?string
    {
        return $this->module;
    }

    /**
     * @return string
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @return mixed
     */
    public function getArg()
    {
        return $this->arg;
    }
}