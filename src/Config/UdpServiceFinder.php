<?php


namespace EasySwoole\Rpc\Config;


class UdpServiceFinder
{
    protected $listenAddress = '0.0.0.0';
    protected $listenPort = 9601;
    protected $broadcastAddress = ['127.0.0.1'];
    protected $broadcastInterval = 5000;
    protected $enableListen = true;
    protected $enableBroadcast = true;
    /** @var null|string */
    protected $signatureKey = null;

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
     * @return string[]
     */
    public function getBroadcastAddress(): array
    {
        return $this->broadcastAddress;
    }

    /**
     * @param string[] $broadcastAddress
     */
    public function setBroadcastAddress(array $broadcastAddress): void
    {
        $this->broadcastAddress = $broadcastAddress;
    }

    /**
     * @return int
     */
    public function getBroadcastInterval(): int
    {
        return $this->broadcastInterval;
    }

    /**
     * @param int $broadcastInterval
     */
    public function setBroadcastInterval(int $broadcastInterval): void
    {
        $this->broadcastInterval = $broadcastInterval;
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
     * @return string|null
     */
    public function getSignatureKey(): ?string
    {
        return $this->signatureKey;
    }

    /**
     * @param string|null $signatureKey
     */
    public function setSignatureKey(?string $signatureKey): void
    {
        $this->signatureKey = $signatureKey;
    }
}