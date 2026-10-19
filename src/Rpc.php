<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\Client\Client;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;

class Rpc
{
    // Rpc 只负责组装配置与依赖，不持有请求状态，因此调用方可自行决定其生命周期。
    private Server $server;
    private RuntimeErrorReporter $runtimeErrorReporter;

    public function __construct(
        private readonly RpcConfig $config = new RpcConfig(),
        private readonly RpcDependencies $dependencies = new RpcDependencies(),
    )
    {
        $this->runtimeErrorReporter = new RuntimeErrorReporter();
        $this->server = new Server(
            $this->dependencies->getNodeManager(),
            $this->config->getServer(),
            $this->config->getDiscovery(),
            $this->config->getSecurity(),
            $this->config->getProtocol(),
            $this->runtimeErrorReporter,
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
        // Client 不保存调用批次；每次 buildRequest() 都会创建独立的 RequestBatch。
        return new Client(
            $this->getNodeManager(),
            $this->dependencies->getNodeSelector(),
            $this->dependencies->getFailurePolicy(),
            $this->config->getSecurity(),
            $this->config->getProtocol(),
            $this->runtimeErrorReporter,
        );
    }

    public function setOnRuntimeError(?callable $callback): static
    {
        $this->server->setOnRuntimeError($callback);
        return $this;
    }

}
