<?php


namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;
use EasySwoole\Utility\Random;

class Config extends SplBean
{
    protected $serverIp;
    protected $listenAddress = '0.0.0.0';
    protected $listenPort = 9600;
    protected $workerNum = 4;
    protected $nodeId;

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

    protected function initialize(): void
    {
        if(empty($this->nodeId)){
            $this->nodeId = Random::character(8);
        }
    }

}