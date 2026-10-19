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
        $node = new ServiceNode(
            serviceName: $name,
            nodeId: $this->nodeId,
            nodeName: $this->config->nodeName,
            nodeWeight: $weight,
            ip: $this->config->advertiseIp,
            port: $this->config->resolvedAdvertisePort(),
        );
        // 注册完成后同时冻结 Service，确保广播出去的服务定义不会在运行中继续变化。
        $service->freeze();
        $this->services[$name] = $service;
        $this->nodes[$name] = $node;
    }

    // attachServer() 一开始即冻结 Registry，防止 fork 后各进程持有不同服务列表。
    public function freeze(): void { $this->frozen = true; }
    public function find(string $name): ?AbstractService { return $this->services[$name] ?? null; }
    public function node(string $name): ?ServiceNode { return $this->nodes[$name] ?? null; }
    public function nodes(): array { return $this->nodes; }
    public function online(string $name): bool { return $this->nodeManager->online($this->requireNode($name)); }
    public function heartbeat(string $name): bool { return $this->nodeManager->heartbeat($this->requireNode($name)); }
    public function offline(string $name): bool { return $this->nodeManager->offline($this->requireNode($name)); }
    private function requireNode(string $name): ServiceNode { return $this->nodes[$name] ?? throw new RpcException(sprintf('Service "%s" is not registered.', $name)); }
}
