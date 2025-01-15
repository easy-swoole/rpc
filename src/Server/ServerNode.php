<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Spl\SplBean;

class ServerNode extends SplBean
{
    protected string $nodeId;

    protected string $nodeName;

    protected int $nodeWeight = 10;

    protected string $ip;

    protected int $port = 9502;

    protected string $listenAddress = '0.0.0.0';

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function setNodeId(string $nodeId): void
    {
        $this->nodeId = $nodeId;
    }

    public function getNodeName(): string
    {
        return $this->nodeName;
    }

    public function setNodeName(string $nodeName): void
    {
        $this->nodeName = $nodeName;
    }

    public function getNodeWeight(): int
    {
        return $this->nodeWeight;
    }

    public function setNodeWeight(int $nodeWeight): void
    {
        $this->nodeWeight = $nodeWeight;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): void
    {
        $this->ip = $ip;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    public function getListenAddress(): string
    {
        return $this->listenAddress;
    }

    public function setListenAddress(string $listenAddress): void
    {
        $this->listenAddress = $listenAddress;
    }
}