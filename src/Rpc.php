<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\Client\Client;
use EasySwoole\Rpc\Client\TcpTransport;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\Server\ServerConfig;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use EasySwoole\Rpc\Utility\WeightedRandomNodeSelector;
use EasySwoole\Rpc\Client\ConsecutiveFailurePolicy;

class Rpc
{
    private Server $server;

    private AbstractNodeManager $nodeManager;
    private NodeSelectorInterface $nodeSelector;
    private NodeFailurePolicyInterface $failurePolicy;

    public function __construct(
        ?AbstractNodeManager $nodeManager = null,
        ?ServerConfig $serverConfig = null,
        ?NodeSelectorInterface $nodeSelector = null,
        ?NodeFailurePolicyInterface $failurePolicy = null,
    )
    {
        $this->nodeManager = $nodeManager ?? new SwooleTableNodeManager();
        $this->nodeSelector = $nodeSelector ?? new WeightedRandomNodeSelector();
        $this->failurePolicy = $failurePolicy ?? new ConsecutiveFailurePolicy();
        $this->server = new Server($this->nodeManager, $serverConfig ?? new ServerConfig());
    }

    public function getNodeManager(): AbstractNodeManager
    {
        return $this->nodeManager;
    }

    public function server(): Server
    {
        return $this->server;
    }

    public function client(): Client
    {
        return (new Client(
            $this->getNodeManager(),
            $this->nodeSelector,
            $this->failurePolicy,
        ))->setTransport(new TcpTransport());
    }

}
