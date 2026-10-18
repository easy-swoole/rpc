<?php

namespace EasySwoole\Rpc\Utility;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Server\ServiceNode;

final class MemoryNodeManager extends AbstractNodeManager
{
    /** @var array<string, array<string, ServiceNode>> */
    private array $nodes = [];
    /** @var array<string, array<string, ServiceNode>> */
    private array $hibernated = [];

    public function heartbeat(ServiceNode $node): bool
    {
        return $this->online($node);
    }

    public function online(ServiceNode $node): bool
    {
        $this->nodes[$node->getServiceName()][$node->getNodeId()] = $node;
        unset($this->hibernated[$node->getServiceName()][$node->getNodeId()]);
        return true;
    }

    public function offline(ServiceNode $node): bool
    {
        unset($this->nodes[$node->getServiceName()][$node->getNodeId()], $this->hibernated[$node->getServiceName()][$node->getNodeId()]);
        return true;
    }

    public function hibernate(ServiceNode $node): bool
    {
        unset($this->nodes[$node->getServiceName()][$node->getNodeId()]);
        $this->hibernated[$node->getServiceName()][$node->getNodeId()] = $node;
        return true;
    }

    public function getNodes(string $serviceName): array
    {
        return array_values($this->nodes[$serviceName] ?? []);
    }
}
