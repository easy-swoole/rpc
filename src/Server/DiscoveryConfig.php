<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Exception\RpcException;

final class DiscoveryConfig
{
    /** @var list<array{string, int}> */
    private array $broadcastTargets = [];

    /** @param list<string> $broadcastAddresses address entries in host:port form */
    public function __construct(
        public readonly bool $listen = false,
        public readonly string $listenAddress = '0.0.0.0',
        public readonly int $listenPort = 9600,
        public readonly bool $broadcast = false,
        public readonly array $broadcastAddresses = ['255.255.255.255:9600'],
        public readonly int $broadcastIntervalMs = 5000,
        public readonly int $socketRetryIntervalMs = 5000,
    ) {
        if ($this->listen && trim($this->listenAddress) === '') {
            throw new RpcException('Discovery listen address cannot be empty when UDP listening is enabled.');
        }
        if ($this->listen && ($this->listenPort < 1 || $this->listenPort > 65535)) {
            throw new RpcException('Discovery listen port must be between 1 and 65535.');
        }
        if ($this->broadcast && $this->broadcastIntervalMs < 1) {
            throw new RpcException('Discovery broadcast interval must be at least 1 millisecond.');
        }
        if (($this->listen || $this->broadcast) && $this->socketRetryIntervalMs < 1) {
            throw new RpcException('Discovery socket retry interval must be at least 1 millisecond.');
        }
        if ($this->broadcast && $this->broadcastAddresses === []) {
            throw new RpcException('At least one discovery broadcast address is required when broadcasting is enabled.');
        }
        if ($this->broadcast) {
            foreach ($this->broadcastAddresses as $address) {
                if (!is_string($address) || !preg_match('/^.+:([1-9][0-9]{0,4})$/', $address, $matches)
                    || (int) $matches[1] > 65535) {
                    throw new RpcException('Discovery broadcast addresses must use host:port format with a valid port.');
                }
                $position = strrpos($address, ':');
                $this->broadcastTargets[] = [
                    substr($address, 0, $position),
                    (int) substr($address, $position + 1),
                ];
            }
        }
    }

    /** @return list<array{string, int}> */
    public function broadcastTargets(): array
    {
        return $this->broadcastTargets;
    }
}
