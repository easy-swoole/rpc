<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-02-24
 * Time: 12:27
 */

namespace EasySwoole\Rpc\AutoFind;


use EasySwoole\Rpc\Exception\Exception;
use EasySwoole\Spl\SplBean;

class ProcessConfig extends SplBean
{
    /**
     * 广播地址
     * @var array
     */
    protected $autoFindBroadcastAddress = ['127.0.0.1:9600'];

    /**
     * 监听地址
     * @var string
     */
    protected $autoFindListenAddress = '127.0.0.1:9600';

    /**
     * 秘钥
     * @var null
     */
    protected $encryptKey = null;
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

    /**
     * @return null
     */
    public function getEncryptKey()
    {
        return $this->encryptKey;
    }

    public function setEncryptKey($encryptKey): void
    {
        $this->encryptKey = $encryptKey;
    }
}