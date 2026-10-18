<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\Client\Client;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Client\TcpTransport;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\Protocol\ProtocolCodec;

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
            $this->config->getProtocol(),
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
        $codec = new ProtocolCodec(new PayloadSigner($this->config->getSecurity()));
        return (new Client(
            $this->getNodeManager(),
            $this->dependencies->getNodeSelector(),
            $this->dependencies->getFailurePolicy(),
        ))->setTransport($this->dependencies->getTransport() ?? new TcpTransport($codec, $this->config->getProtocol()));
    }

}
