<?php


namespace EasySwoole\Rpc\Network;


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