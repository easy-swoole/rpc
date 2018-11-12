<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午2:55
 */

namespace EasySwoole\Rpc;



use EasySwoole\Utility\Random;

class Config
{
    private $servicePort = 9601;
    private $authKey;
    private $isSubServerMode = true;
    private $listenAddress = '0.0.0.0';
    private $nodeId;
    private $maxPackage = 1024*1024;
    private $heartbeatIdleTime = 30;
    private $heartbeatCheckInterval = 30;
    private $actionMiss;
    private $onException;
    private $maxNodeNum = 4096;

    function __construct()
    {
        $this->nodeId = Random::character(8);
        $this->actionMiss = function (\swoole_server $server, int $fd, ?string $action, RequestPackage $package){

        };
        $this->onException = function (\Throwable $throwable, \swoole_server $server, int $fd, RequestPackage $package,Response $response){
            $response->setStatus($response::STATUS_SERVER_ERROR);
            $response->setMessage("{$throwable->getMessage()} at file {$throwable->getFile()} line {$throwable->getLine()}");
        };
    }

    public function onException(callable $callback)
    {
        $this->onException = $callback;
    }

    public function onActionMiss(callable $callback)
    {
        $this->actionMiss = $callback;
        return $this;
    }

    /**
     * @return callable
     */
    public function getOnActionMiss()
    {
        return $this->actionMiss;
    }

    /**
     * @return callable
     */
    public function getOnException()
    {
        return $this->onException;
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
     * @return mixed
     */
    public function getAuthKey()
    {
        return $this->authKey;
    }

    /**
     * @param mixed $authKey
     */
    public function setAuthKey($authKey): void
    {
        $this->authKey = $authKey;
    }

    /**
     * @return bool
     */
    public function isSubServerMode(): bool
    {
        return $this->isSubServerMode;
    }

    /**
     * @param bool $isSubServerMode
     */
    public function setIsSubServerMode(bool $isSubServerMode): void
    {
        $this->isSubServerMode = $isSubServerMode;
    }

    /**
     * @return string
     */
    public function getListenAddress(): string
    {
        return $this->listenAddress;
    }

    /**
     * @param string $listenAddress
     */
    public function setListenAddress(string $listenAddress): void
    {
        $this->listenAddress = $listenAddress;
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
     * @return int
     */
    public function getHeartbeatIdleTime(): int
    {
        return $this->heartbeatIdleTime;
    }

    /**
     * @param int $heartbeatIdleTime
     */
    public function setHeartbeatIdleTime(int $heartbeatIdleTime): void
    {
        $this->heartbeatIdleTime = $heartbeatIdleTime;
    }

    /**
     * @return int
     */
    public function getHeartbeatCheckInterval(): int
    {
        return $this->heartbeatCheckInterval;
    }

    /**
     * @param int $heartbeatCheckInterval
     */
    public function setHeartbeatCheckInterval(int $heartbeatCheckInterval): void
    {
        $this->heartbeatCheckInterval = $heartbeatCheckInterval;
    }

    /**
     * @return int
     */
    public function getMaxNodeNum(): int
    {
        return $this->maxNodeNum;
    }

    /**
     * @param int $maxNodeNum
     */
    public function setMaxNodeNum(int $maxNodeNum): void
    {
        $this->maxNodeNum = $maxNodeNum;
    }


}