<?php

namespace EasySwoole\Rpc\Server;

final class ServiceNode implements \JsonSerializable
{
    public function __construct(
        private readonly string $serviceName,
        private readonly string $nodeId = '',
        private readonly string $nodeName = '',
        private readonly int $nodeWeight = 10,
        private readonly string $ip = '',
        private readonly int $port = 9502,
        private readonly string $listenAddress = '0.0.0.0',
    ) {
        if ($serviceName === '') {
            throw new \InvalidArgumentException('Service name cannot be empty.');
        }
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Service node port must be between 1 and 65535.');
        }
        if ($nodeWeight < 0) {
            throw new \InvalidArgumentException('Service node weight cannot be negative.');
        }
    }

    public function getServiceName(): string { return $this->serviceName; }
    public function getNodeId(): string { return $this->nodeId; }
    public function getNodeName(): string { return $this->nodeName; }
    public function getNodeWeight(): int { return $this->nodeWeight; }
    public function getIp(): string { return $this->ip; }
    public function getPort(): int { return $this->port; }
    public function getListenAddress(): string { return $this->listenAddress; }

    public function withNodeId(string $nodeId): self
    {
        return new self(
            serviceName: $this->serviceName,
            nodeId: $nodeId,
            nodeName: $this->nodeName,
            nodeWeight: $this->nodeWeight,
            ip: $this->ip,
            port: $this->port,
            listenAddress: $this->listenAddress,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'serviceName' => $this->serviceName,
            'nodeId' => $this->nodeId,
            'nodeName' => $this->nodeName,
            'nodeWeight' => $this->nodeWeight,
            'ip' => $this->ip,
            'port' => $this->port,
            'listenAddress' => $this->listenAddress,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            serviceName: (string) ($data['serviceName'] ?? ''),
            nodeId: (string) ($data['nodeId'] ?? ''),
            nodeName: (string) ($data['nodeName'] ?? ''),
            nodeWeight: (int) ($data['nodeWeight'] ?? 10),
            ip: (string) ($data['ip'] ?? ''),
            port: (int) ($data['port'] ?? 9502),
            listenAddress: (string) ($data['listenAddress'] ?? '0.0.0.0'),
        );
    }
}
