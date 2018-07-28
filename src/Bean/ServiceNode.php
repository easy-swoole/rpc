<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午4:17
 */

namespace EasySwoole\Rpc\Bean;


use EasySwoole\Spl\SplBean;

class ServiceNode extends SplBean
{
    protected $serviceName;
    protected $serviceId;
    protected $ip;
    protected $port;
    protected $lastHeartBeat;
    protected $isLocal = 0;
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
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * @param mixed $serviceId
     */
    public function setServiceId($serviceId): void
    {
        $this->serviceId = $serviceId;
    }

    /**
     * @return mixed
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param mixed $ip
     */
    public function setIp($ip): void
    {
        $this->ip = $ip;
    }

    /**
     * @return mixed
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param mixed $port
     */
    public function setPort($port): void
    {
        $this->port = $port;
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
     * @return int
     */
    public function getisLocal(): int
    {
        return $this->isLocal;
    }

    /**
     * @param int $isLocal
     */
    public function setIsLocal(int $isLocal): void
    {
        $this->isLocal = $isLocal;
    }

}