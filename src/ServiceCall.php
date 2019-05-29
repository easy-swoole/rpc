<?php


namespace EasySwoole\Rpc;



class ServiceCall extends Request
{

    protected $serviceVersion;
    protected $serviceNode;
    protected $onSuccess;
    protected $onFail;

    /**
     * @return mixed
     */
    public function getServiceVersion()
    {
        return $this->serviceVersion;
    }

    public function setServiceVersion($serviceVersion):ServiceCall
    {
        $this->serviceVersion = $serviceVersion;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getServiceNode():?ServiceNode
    {
        return $this->serviceNode;
    }

    public function setServiceNode(ServiceNode $serviceNode):ServiceCall
    {
        $this->serviceNode = $serviceNode;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOnSuccess():?callable
    {
        return $this->onSuccess;
    }


    public function setOnSuccess(callable $onSuccess):ServiceCall
    {
        $this->onSuccess = $onSuccess;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOnFail():?callable
    {
        return $this->onFail;
    }

    public function setOnFail(callable $onFail):ServiceCall
    {
        $this->onFail = $onFail;
        return $this;
    }

}