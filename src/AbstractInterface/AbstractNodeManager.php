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

}
