<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\SecurityConfig;

final class AssistWorkerConfig
{
    public function __construct(
        public readonly string $nodeName = '',
        public readonly int $heartbeatIntervalMs = 5000,
        public readonly DiscoveryConfig $discovery = new DiscoveryConfig(),
        public readonly SecurityConfig $security = new SecurityConfig(),
    ) {
        if ($this->heartbeatIntervalMs < 1) {
            throw new RpcException('Assist worker heartbeat interval must be at least 1 millisecond.');
        }
    }

    public static function fromServer(
        ServerConfig $server,
        DiscoveryConfig $discovery,
        SecurityConfig $security,
    ): self {
        return new self(
            nodeName: $server->nodeName,
            heartbeatIntervalMs: $server->heartbeatIntervalMs,
            discovery: $discovery,
            security: $security,
        );
    }
}
