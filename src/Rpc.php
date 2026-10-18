<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\Client\Client;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;

class Rpc
{
    private Server $server;

    public function __construct(
        private readonly RpcConfig $config = new RpcConfig(),
        private readonly RpcDependencies $dependencies = new RpcDependencies(),
    )
    {
        $this->server = new Server(
            $this->dependencies->getNodeManager(),
            $this->config->getServer(),
            $this->config->getDiscovery(),
            $this->config->getSecurity(),
        );
    }

    public function getNodeManager(): AbstractNodeManager
    {
        return $this->dependencies->getNodeManager();
    }

    public function server(): Server
    {
        return $this->server;
    }

    public function client(): Client
    {
        return (new Client(
            $this->getNodeManager(),
            $this->dependencies->getNodeSelector(),
            $this->dependencies->getFailurePolicy(),
            $this->config->getSecurity(),
        ))->setTransport($this->dependencies->getTransport());
    }

}
