<?php

namespace EasySwoole\Rpc\Server;

final class DiscoveryConfig
{
    /** @param list<string> $broadcastAddresses address entries in host:port form */
    public function __construct(
        public readonly bool $listen = true,
        public readonly string $listenAddress = '0.0.0.0',
        public readonly int $listenPort = 9600,
        public readonly bool $broadcast = true,
        public readonly array $broadcastAddresses = ['255.255.255.255:9600'],
        public readonly int $heartbeatIntervalMs = 5000,
        public readonly int $nodeTtlSeconds = 15,
        public readonly ?string $secret = null,
    ) {}
}
