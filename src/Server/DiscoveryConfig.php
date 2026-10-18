<?php

namespace EasySwoole\Rpc\Server;

final class DiscoveryConfig
{
    /** @param list<string> $broadcastAddresses address entries in host:port form */
    public function __construct(
        public bool $listen = true,
        public string $listenAddress = '0.0.0.0',
        public int $listenPort = 9600,
        public bool $broadcast = true,
        public array $broadcastAddresses = ['255.255.255.255:9600'],
        public int $heartbeatIntervalMs = 5000,
        public int $nodeTtlSeconds = 15,
        public ?string $secret = null,
    ) {}
}
