<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\Client\Client;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;

class Rpc
{
    // Rpc 只负责组装配置与依赖，不持有请求状态，因此调用方可自行决定其生命周期。
    private Server $server;
    private Client $client;

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
        $this->client = new Client(
            $this->dependencies->getNodeManager(),
            $this->dependencies->getNodeSelector(),
            $this->dependencies->getFailurePolicy(),
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
        // Client 本身不保存调用批次；并发隔离由每次 buildRequest() 创建的 RequestBatch 提供。
        return $this->client;
    }

}
