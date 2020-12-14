<?php


namespace EasySwoole\Rpc\Config;


class Server
{
    /** @var string */
    protected $serverIp;
    protected $listenAddress = '0.0.0.0';
    protected $listenPort = 9600;
    protected $workerNum = 4;
    protected $maxPackageSize = 1024*1024*2;//2M
    protected $networkReadTimeout = 3;
    /**
     * @return string
     */
    public function getServerIp(): ?string
    {
        return $this->serverIp;
    }

    /**
     * @param string $serverIp
     */
    public function setServerIp(string $serverIp): void
    {
        $this->serverIp = $serverIp;
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
     * @return float|int
     */
    public function getMaxPackageSize()
    {
        return $this->maxPackageSize;
    }

    /**
     * @param float|int $maxPackageSize
     */
    public function setMaxPackageSize($maxPackageSize): void
    {
        $this->maxPackageSize = $maxPackageSize;
    }

    /**
     * @return int
     */
    public function getNetworkReadTimeout(): int
    {
        return $this->networkReadTimeout;
    }

    /**
     * @param int $networkReadTimeout
     */
    public function setNetworkReadTimeout(int $networkReadTimeout): void
    {
        $this->networkReadTimeout = $networkReadTimeout;
    }
}