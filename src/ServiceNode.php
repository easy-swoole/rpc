<?php


namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class ServiceNode extends SplBean
{
    protected $serviceIp;
    protected $servicePort;
    protected $serviceVersion;
    protected $serviceName;
    protected $nodeExpire = null;
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
     * @return null
     */
    public function getNodeExpire()
    {
        return $this->nodeExpire;
    }

    /**
     * @param null $nodeExpire
     */
    public function setNodeExpire($nodeExpire): void
    {
        $this->nodeExpire = $nodeExpire;
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