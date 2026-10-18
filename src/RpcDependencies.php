<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\AbstractInterface\TransportInterface;
use EasySwoole\Rpc\Client\ConsecutiveFailurePolicy;
use EasySwoole\Rpc\Client\TcpTransport;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use EasySwoole\Rpc\Utility\WeightedRandomNodeSelector;

final class RpcDependencies
{
    public function __construct(
        private readonly AbstractNodeManager $nodeManager = new SwooleTableNodeManager(),
        private readonly NodeSelectorInterface $nodeSelector = new WeightedRandomNodeSelector(),
        private readonly NodeFailurePolicyInterface $failurePolicy = new ConsecutiveFailurePolicy(),
        private readonly TransportInterface $transport = new TcpTransport(),
    ) {}

    public function getNodeManager(): AbstractNodeManager
    {
        return $this->nodeManager;
    }

    public function getNodeSelector(): NodeSelectorInterface
    {
        return $this->nodeSelector;
    }

    public function getFailurePolicy(): NodeFailurePolicyInterface
    {
        return $this->failurePolicy;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }
}
