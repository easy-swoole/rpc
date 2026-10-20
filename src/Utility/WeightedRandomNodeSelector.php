<?php

namespace EasySwoole\Rpc\Utility;

use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\Server\ServiceNode;

final class WeightedRandomNodeSelector implements NodeSelectorInterface
{
    public function select(array $nodes): ?ServiceNode
    {
        if ($nodes === []) {
            return null;
        }
        $total = array_sum(array_map(
            static fn (ServiceNode $node): int => $node->getNodeWeight(),
            $nodes,
        ));
        $cursor = random_int(1, $total);
        foreach ($nodes as $node) {
            $cursor -= $node->getNodeWeight();
            if ($cursor <= 0) {
                return $node;
            }
        }
        return $nodes[0];
    }
}
