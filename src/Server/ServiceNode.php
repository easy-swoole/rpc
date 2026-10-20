<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Exception\RpcException;

final class ServiceNode implements \JsonSerializable
{
    public function __construct(
        private readonly string $serviceName,
        private readonly string $nodeId,
        private readonly string $nodeName = '',
        private readonly int $nodeWeight = 10,
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 9502,
    ) {
        if ($serviceName === '') {
            throw new RpcException('Service name cannot be empty.');
        }
        if ($nodeId === '') {
            throw new RpcException('Service node ID cannot be empty.');
        }
        if (trim($host) === '') {
            throw new RpcException('Service node host cannot be empty.');
        }
        if ($port < 1 || $port > 65535) {
            throw new RpcException('Service node port must be between 1 and 65535.');
        }
        if ($nodeWeight < 1) {
            throw new RpcException('Service node weight must be at least 1.');
        }
    }

    public function getServiceName(): string { return $this->serviceName; }
    public function getNodeId(): string { return $this->nodeId; }
    public function getNodeName(): string { return $this->nodeName; }
    public function getNodeWeight(): int { return $this->nodeWeight; }
    public function getHost(): string { return $this->host; }
    public function getPort(): int { return $this->port; }

    public function jsonSerialize(): array
    {
        return [
            'serviceName' => $this->serviceName,
            'nodeId' => $this->nodeId,
            'nodeName' => $this->nodeName,
            'nodeWeight' => $this->nodeWeight,
            'host' => $this->host,
            'port' => $this->port,
        ];
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['serviceName'], $data['nodeId'], $data['nodeName'], $data['nodeWeight'], $data['host'], $data['port'])
            || !is_string($data['serviceName'])
            || !is_string($data['nodeId'])
            || !is_string($data['nodeName'])
            || !is_int($data['nodeWeight'])
            || !is_string($data['host'])
            || !is_int($data['port'])) {
            throw new RpcException('Service node contains invalid or missing fields.');
        }
        return new self(
            serviceName: $data['serviceName'],
            nodeId: $data['nodeId'],
            nodeName: $data['nodeName'],
            nodeWeight: $data['nodeWeight'],
            host: $data['host'],
            port: $data['port'],
        );
    }
}
