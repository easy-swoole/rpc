<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\AbstractService;
use EasySwoole\Rpc\Exception\RpcException;

final class ServiceRegistry
{
    private array $services = [];
    private array $nodes = [];
    private bool $frozen = false;

    public function __construct(private readonly AbstractNodeManager $nodeManager, private readonly ServerConfig $config, private readonly string $nodeId) {}

    public function register(AbstractService $service): void
    {
        if ($this->frozen) { throw new RpcException('Services cannot be registered after RPC Server attachment has started.'); }
        $name = $service->serviceName();
        if (isset($this->services[$name])) { throw new RpcException(sprintf('Service "%s" is already registered.', $name)); }
        $weight = $service->serviceWeight();
        if ($weight < 0) { throw new RpcException(sprintf('Service "%s" weight cannot be negative.', $name)); }
        $node = new ServiceNode($name, $this->nodeId, $this->config->nodeName, $weight, $this->config->advertiseIp, $this->config->resolvedAdvertisePort(), $this->config->listenAddress);
        $service->freeze();
        $this->services[$name] = $service;
        $this->nodes[$name] = $node;
    }

    public function freeze(): void { $this->frozen = true; }
    public function find(string $name): ?AbstractService { return $this->services[$name] ?? null; }
    public function node(string $name): ?ServiceNode { return $this->nodes[$name] ?? null; }
    public function nodes(): array { return $this->nodes; }
    public function online(string $name): bool { return $this->nodeManager->online($this->requireNode($name)); }
    public function heartbeat(string $name): bool { return $this->nodeManager->heartbeat($this->requireNode($name)); }
    public function offline(string $name): bool { return $this->nodeManager->offline($this->requireNode($name)); }
    private function requireNode(string $name): ServiceNode { return $this->nodes[$name] ?? throw new RpcException(sprintf('Service "%s" is not registered.', $name)); }
}
