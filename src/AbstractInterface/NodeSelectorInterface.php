<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Server\ServiceNode;

interface NodeSelectorInterface
{
    /** @param list<ServiceNode> $nodes */
    public function select(array $nodes): ?ServiceNode;
}
