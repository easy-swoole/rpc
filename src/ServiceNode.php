<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/12
 * Time: 10:33 PM
 */

namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class ServiceNode extends SplBean
{
    protected $serviceIp;
    protected $servicePort;
    protected $serviceBroadcastPort;
    protected $serviceVersion;
    protected $serviceName;
    protected $nodeExpire;
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
     * @return mixed
     */
    public function getServicePort()
    {
        return $this->servicePort;
    }

    /**
     * @param mixed $servicePort
     */
    public function setServicePort($servicePort): void
    {
        $this->servicePort = $servicePort;
    }

    /**
     * @return mixed
     */
    public function getServiceVersion()
    {
        return $this->serviceVersion;
    }

    /**
     * @param mixed $serviceVersion
     */
    public function setServiceVersion($serviceVersion): void
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
    public function getNodeExpire()
    {
        return $this->nodeExpire;
    }

    /**
     * @param mixed $nodeExpire
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

    /**
     * @return mixed
     */
    public function getServiceBroadcastPort()
    {
        return $this->serviceBroadcastPort;
    }

    /**
     * @param mixed $serviceBroadcastPort
     */
    public function setServiceBroadcastPort($serviceBroadcastPort): void
    {
        $this->serviceBroadcastPort = $serviceBroadcastPort;
    }

}