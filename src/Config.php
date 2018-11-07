<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午2:55
 */

namespace EasySwoole\Rpc;



class Config
{
    private $servicePort = 9601;
    private $authKey;
    private $isSubServerMode = true;
    private $listenAddress = '0.0.0.0';
    private $nodeId;
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

}