<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午2:55
 */

namespace EasySwoole\Rpc;


use EasySwoole\Rpc\Bean\BroadcastList;
use EasySwoole\Utility\Random;

class Config
{
    private $servicePort = 9601;
    private $serviceId;
    private $listenHost = '0.0.0.0';
    private $subServerMode = true;
    private $enableBroadcast = false;
    private $broadcastListenPort = 9602;
    private $broadcastList = null;
    private $maxNodes = 2048;
    private $maxPackage = 1024*64;
    private $secretKey = '';
    private $heartbeat_idle_time = 5;
    private $heartbeat_check_interval = 30;

    function __construct()
    {
        $this->serviceId = Random::character(8);
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
     * @return bool
     */
    public function isSubServerMode(): bool
    {
        return $this->subServerMode;
    }

    /**
     * @param bool $subServerMode
     */
    public function setSubServerMode(bool $subServerMode): void
    {
        $this->subServerMode = $subServerMode;
    }

    /**
     * @return bool
     */
    public function isEnableBroadcast(): bool
    {
        return $this->enableBroadcast;
    }

    /**
     * @param bool $enableBroadcast
     */
    public function setEnableBroadcast(bool $enableBroadcast): void
    {
        $this->enableBroadcast = $enableBroadcast;
    }

    /**
     * @return int
     */
    public function getBroadcastListenPort(): int
    {
        return $this->broadcastListenPort;
    }

    /**
     * @param int $broadcastListenPort
     */
    public function setBroadcastListenPort(int $broadcastListenPort): void
    {
        $this->broadcastListenPort = $broadcastListenPort;
    }

    public function getBroadcastList():BroadcastList
    {
        if(!isset($this->broadcastList)){
            $this->broadcastList = new BroadcastList();
        }
        return $this->broadcastList;
    }

    /**
     * @param $broadcastList
     */
    public function setBroadcastList(BroadcastList $broadcastList): void
    {
        $this->broadcastList = $broadcastList;
    }

    /**
     * @return int
     */
    public function getMaxNodes(): int
    {
        return $this->maxNodes;
    }

    /**
     * @param int $maxNodes
     */
    public function setMaxNodes(int $maxNodes): void
    {
        $this->maxNodes = $maxNodes;
    }

    /**
     * @return string
     */
    public function getListenHost(): string
    {
        return $this->listenHost;
    }

    /**
     * @param string $listenHost
     */
    public function setListenHost(string $listenHost): void
    {
        $this->listenHost = $listenHost;
    }

    /**
     * @return float|int
     */
    public function getMaxPackage()
    {
        return $this->maxPackage;
    }

    /**
     * @param float|int $maxPackage
     */
    public function setMaxPackage($maxPackage): void
    {
        $this->maxPackage = $maxPackage;
    }

    /**
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * @param string $secretKey
     */
    public function setSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }

    /**
     * @return int
     */
    public function getHeartbeatIdleTime(): int
    {
        return $this->heartbeat_idle_time;
    }

    /**
     * @param int $heartbeat_idle_time
     */
    public function setHeartbeatIdleTime(int $heartbeat_idle_time): void
    {
        $this->heartbeat_idle_time = $heartbeat_idle_time;
    }

    /**
     * @return int
     */
    public function getHeartbeatCheckInterval(): int
    {
        return $this->heartbeat_check_interval;
    }

    /**
     * @param int $heartbeat_check_interval
     */
    public function setHeartbeatCheckInterval(int $heartbeat_check_interval): void
    {
        $this->heartbeat_check_interval = $heartbeat_check_interval;
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
}