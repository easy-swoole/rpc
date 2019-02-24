<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-02-24
 * Time: 12:27
 */

namespace EasySwoole\Rpc\AutoFind;


use EasySwoole\Spl\SplBean;

class ProcessConfig extends SplBean
{
    protected $autoFindBroadcastAddress = [
        '127.0.0.1:9600'
    ];

    protected $autoFindListenAddress = '127.0.0.1:9600';
    /**
     * @return array
     */
    public function getAutoFindBroadcastAddress(): array
    {
        return $this->autoFindBroadcastAddress;
    }

    /**
     * @param array $autoFindBroadcastAddress
     */
    public function setAutoFindBroadcastAddress(array $autoFindBroadcastAddress): void
    {
        $this->autoFindBroadcastAddress = $autoFindBroadcastAddress;
    }

    /**
     * @return string
     */
    public function getAutoFindListenAddress(): ?string
    {
        return $this->autoFindListenAddress;
    }

    /**
     * @param string $autoFindListenAddress
     */
    public function setAutoFindListenAddress(?string $autoFindListenAddress = null): void
    {
        $this->autoFindListenAddress = $autoFindListenAddress;
    }
}