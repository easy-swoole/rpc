<?php


namespace EasySwoole\Rpc;


use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use EasySwoole\Spl\SplBean;
use EasySwoole\Utility\Random;
use EasySwoole\Pool\Config as PoolConfig;

class Config extends SplBean
{
    protected $serverIp;
    protected $listenAddress = '0.0.0.0';
    protected $listenPort = 9600;
    protected $workerNum = 4;
    protected $nodeId;
    protected $extraConfig;
    protected $nodeManager;
    protected $broadcastConfig;
    protected $onException;
    protected $maxPackage = 1024*8;
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
     * @return int
     */
    public function getListenPort(): int
    {
        return $this->listenPort;
    }

    /**
     * @param int $listenPort
     */
    public function setListenPort(int $listenPort): void
    {
        $this->listenPort = $listenPort;
    }

    /**
     * @return int
     */
    public function getWorkerNum(): int
    {
        return $this->workerNum;
    }

    /**
     * @param int $workerNum
     */
    public function setWorkerNum(int $workerNum): void
    {
        $this->workerNum = $workerNum;
    }

    /**
     * @return mixed
     */
    public function getServerIp()
    {
        return $this->serverIp;
    }

    /**
     * @param mixed $serverIp
     */
    public function setServerIp($serverIp): void
    {
        $this->serverIp = $serverIp;
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
    public function getExtraConfig()
    {
        return $this->extraConfig;
    }

    /**
     * @param mixed $extraConfig
     */
    public function setExtraConfig($extraConfig): void
    {
        $this->extraConfig = $extraConfig;
    }

    /**
     * @return mixed
     */
    public function getNodeManager():?NodeManagerInterface
    {
        return $this->nodeManager;
    }

    /**
     * @param mixed $nodeManager
     */
    public function setNodeManager(NodeManagerInterface $nodeManager): void
    {
        $this->nodeManager = $nodeManager;
    }

    /**
     * @return mixed
     */
    public function getBroadcastConfig():BroadcastConfig
    {
        return $this->broadcastConfig;
    }

    /**
     * @return mixed
     */
    public function getOnException():?callable
    {
        return $this->onException;
    }

    /**
     * @param mixed $onException
     */
    public function setOnException(callable $onException): void
    {
        $this->onException = $onException;
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

    protected function initialize(): void
    {
        if(empty($this->nodeId)){
            $this->nodeId = Random::character(8);
        }
        if(empty($this->broadcastConfig)){
            $this->broadcastConfig = new BroadcastConfig();
        }
    }
}