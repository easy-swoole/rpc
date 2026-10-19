<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Server\ServiceNode;

/**
 * Node manager implementations must be safe to use after Swoole forks.
 *
 * The same instance is created in the parent process and inherited by server
 * workers and RPC custom processes. Shared-memory resources may be initialized
 * before the fork, but process-bound resources such as Redis/Etcd clients,
 * sockets and coroutine clients must be connected lazily in the child process
 * and recreated whenever the current PID differs from their owner PID.
 */
abstract class AbstractNodeManager
{
    /**
     * Runtime failures such as a temporarily unavailable registry must return
     * false instead of throwing. Callers will report the failure and keep the
     * normal heartbeat/retry lifecycle running so the node can recover later.
     */
    abstract public function heartbeat(ServiceNode $node): bool;
    abstract public function offline(ServiceNode $node): bool;
    abstract public function online(ServiceNode $node): bool;

    /**
     * @return list<ServiceNode>|false An empty list means that the query
     * succeeded with no available nodes; false means the registry query failed.
     */
    abstract public function getNodes(string $serviceName): array|false;

}
