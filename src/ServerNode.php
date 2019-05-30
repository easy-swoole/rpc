<?php


namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class ServerNode extends SplBean
{
    protected $nodeId;
    protected $serverIp;
    protected $serverPort;

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
    public function getServerPort()
    {
        return $this->serverPort;
    }

    /**
     * @param mixed $serverPort
     */
    public function setServerPort($serverPort): void
    {
        $this->serverPort = $serverPort;
    }
}