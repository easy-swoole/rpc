<?php


namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class ServiceNode extends SplBean
{
    protected $serviceIp;
    protected $servicePort;
    protected $serviceVersion;
    protected $serviceName;
    protected $lastHeartBeat;
    protected $nodeId;

    /**
     * @return mixed
     */
    public function getServiceIp()
    {
        return $this->serviceIp;
    }

    /**
     * @param mixed $serviceIp
     */
    public function setServiceIp($serviceIp): void
    {
        $this->serviceIp = $serviceIp;
    }

    /**
     * @return int
     */
    public function getServicePort(): int
    {
        return $this->servicePort;
    }

    /**
     * @param int $servicePort
     */
    public function setServicePort(int $servicePort): void
    {
        $this->servicePort = $servicePort;
    }

    /**
     * @return string
     */
    public function getServiceVersion(): string
    {
        return $this->serviceVersion;
    }

    /**
     * @param string $serviceVersion
     */
    public function setServiceVersion(string $serviceVersion): void
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

    /**
     * @return mixed
     */
    public function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * @param mixed $nodeId
     */
    public function setNodeId($nodeId): void
    {
        $this->nodeId = $nodeId;
    }
}