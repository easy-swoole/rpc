<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Server\ServiceNode;

abstract class AbstractNodeManager
{
    abstract public function heartbeat(ServiceNode $node): bool;
    abstract public function offline(ServiceNode $node): bool;
    abstract public function online(ServiceNode $node): bool;
    abstract public function hibernate(ServiceNode $node): bool;

    /** @return list<ServiceNode> */
    abstract public function getNodes(string $serviceName): array;

    public function getNode(string $serviceName): ?ServiceNode
    {
        $nodes = $this->getNodes($serviceName);
        if ($nodes === []) {
            return null;
        }
        $total = array_sum(array_map(static fn (ServiceNode $node): int => max(0, $node->getNodeWeight()), $nodes));
        if ($total === 0) {
            return $nodes[array_rand($nodes)];
        }
        $cursor = random_int(1, $total);
        foreach ($nodes as $node) {
            $cursor -= max(0, $node->getNodeWeight());
            if ($cursor <= 0) {
                return $node;
            }
        }
        return $nodes[0];
    }
}
