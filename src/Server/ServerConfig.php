<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Exception\RpcException;

final class ServerConfig
{
    public function __construct(
        public readonly string $advertiseIp = '127.0.0.1',
        public readonly int $listenPort = 9502,
        public readonly string $listenAddress = '0.0.0.0',
        public readonly ?int $advertisePort = null,
        public readonly ?string $nodeId = null,
        public readonly string $nodeName = '',
        public readonly int $heartbeatIntervalMs = 5000,
    ) {
        self::validatePort($listenPort, 'Listen');
        if ($advertisePort !== null) {
            self::validatePort($advertisePort, 'Advertise');
        }
        if ($advertiseIp === '') {
            throw new RpcException('Advertise IP cannot be empty.');
        }
        if ($nodeId === '') {
            throw new RpcException('Server node ID cannot be empty when configured.');
        }
        if ($heartbeatIntervalMs < 1) {
            throw new RpcException('Heartbeat interval must be at least 1 millisecond.');
        }
    }

    public function resolvedAdvertisePort(): int
    {
        return $this->advertisePort ?? $this->listenPort;
    }

    private static function validatePort(int $port, string $label): void
    {
        if ($port < 1 || $port > 65535) {
            throw new RpcException(sprintf('%s port must be between 1 and 65535.', $label));
        }
    }
}
