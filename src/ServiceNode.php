<?php


namespace EasySwoole\Rpc;



class ServiceNode extends ServerNode
{
    protected $serviceVersion;
    protected $serviceName;
    protected $lastHeartBeat;

    /**
     * @return mixed
     */
    public function getServiceVersion():?string
    {
        return $this->serviceVersion;
    }

    /**
     * @param mixed $serviceVersion
     */
    public function setServiceVersion(?string $serviceVersion): void
    {
        $this->serviceVersion = $serviceVersion;
    }

    /**
     * @return mixed
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @param mixed $serviceName
     */
    public function setServiceName($serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @return mixed
     */
    public function getLastHeartBeat()
    {
        return $this->lastHeartBeat;
    }

    /**
     * @param mixed $lastHeartBeat
     */
    public function setLastHeartBeat($lastHeartBeat): void
    {
        $this->lastHeartBeat = $lastHeartBeat;
    }

}