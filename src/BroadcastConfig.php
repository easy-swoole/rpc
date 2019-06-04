<?php


namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class BroadcastConfig extends SplBean
{
    protected $listenAddress = '0.0.0.0';
    protected $listenPort = 9601;
    protected $broadcastAddress = [
        '127.0.0.1:9601'
    ];
    protected $interval = 5;
    protected $enableListen = false;
    protected $enableBroadcast = false;
    protected $secretKey = '';

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
     * @return array
     */
    public function getBroadcastAddress(): array
    {
        return $this->broadcastAddress;
    }

    /**
     * @param array $broadcastAddress
     */
    public function setBroadcastAddress(array $broadcastAddress): void
    {
        $this->broadcastAddress = $broadcastAddress;
    }

    /**
     * @return int
     */
    public function getInterval(): int
    {
        return $this->interval;
    }

    /**
     * @param int $interval
     */
    public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }

    /**
     * @return bool
     */
    public function isEnableListen(): bool
    {
        return $this->enableListen;
    }

    /**
     * @param bool $enableListen
     */
    public function setEnableListen(bool $enableListen): void
    {
        $this->enableListen = $enableListen;
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


}