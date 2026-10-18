<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\AbstractService;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\Protocol;
use Swoole\Server\Port;

class Server
{
    private array $service = [];

    /** @var array<string, ServiceNode> */
    private array $serviceNodes = [];

    private string $nodeId;

    private AbstractNodeManager $nodeManager;

    public function __construct(
        AbstractNodeManager $nodeManager,
        private readonly ServerConfig $config = new ServerConfig(),
    )
    {
        $this->nodeManager = $nodeManager;
        $this->nodeId = self::uuid();
    }

    public function registerService(AbstractService $service): void
    {
        $serviceName = $service->serviceName();
        if (isset($this->service[$serviceName])) {
            throw new \LogicException(sprintf('Service "%s" is already registered.', $serviceName));
        }
        $serviceWeight = $service->serviceWeight();
        if ($serviceWeight < 0) {
            throw new \InvalidArgumentException(sprintf('Service "%s" weight cannot be negative.', $serviceName));
        }
        $node = new ServiceNode(
            serviceName: $serviceName,
            nodeId: $this->nodeId,
            nodeName: $this->config->nodeName,
            nodeWeight: $serviceWeight,
            ip: $this->config->advertiseIp,
            port: $this->config->resolvedAdvertisePort(),
            listenAddress: $this->config->listenAddress,
        );
        $this->service[$serviceName] = $service;
        $this->serviceNodes[$serviceName] = $node;
    }

    public function getConfig(): ServerConfig
    {
        return $this->config;
    }

    public function createAssistWorker(?DiscoveryConfig $config = null): AssistWorker
    {
        return new AssistWorker(
            $this->serviceNodes,
            $this->nodeManager,
            $config ?? new DiscoveryConfig(),
        );
    }

    public function getServiceNode(string $serviceName): ?ServiceNode
    {
        return $this->serviceNodes[$serviceName] ?? null;
    }

    public function onlineService(string $serviceName): bool
    {
        return $this->nodeManager->online($this->requireServiceNode($serviceName));
    }

    public function heartbeatService(string $serviceName): bool
    {
        return $this->nodeManager->heartbeat($this->requireServiceNode($serviceName));
    }

    public function hibernateService(string $serviceName): bool
    {
        return $this->nodeManager->hibernate($this->requireServiceNode($serviceName));
    }

    public function offlineService(string $serviceName): bool
    {
        return $this->nodeManager->offline($this->requireServiceNode($serviceName));
    }

    /** @return array<string, ServiceNode> */
    public function allServiceNodes(): array
    {
        return $this->serviceNodes;
    }

    function allService():array
    {
        return $this->service;
    }

    function getService(string $serviceName):?AbstractService
    {
        if(isset($this->service[$serviceName])){
            return $this->service[$serviceName];
        }
        return null;
    }

    public function handle(Request $request): Response
    {
        $response = new Response($request->getRequestUUID());
        $service = $this->getService($request->getService());
        if ($service === null) {
            return $response->setStatus(Response::STATUS_SERVICE_NOT_EXIST);
        }

        return $service->__dispatch($request, $response);
    }

    public function attach(\Swoole\Server|Port $listener, int $maxPackageSize = 2097152): self
    {
        if ((int) $listener->port !== $this->config->listenPort) {
            throw new \LogicException(sprintf(
                'Swoole listener uses port %d, but RPC ServerConfig expects port %d.',
                $listener->port,
                $this->config->listenPort,
            ));
        }
        $listener->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
            'package_max_length' => $maxPackageSize,
        ]);
        $listener->on('receive', function (\Swoole\Server $server, int $fd, int $reactorId, string $packet): void {
            try {
                $request = Request::fromJson(Protocol::unpack($packet));
                $response = $this->handle($request);
            } catch (\Throwable $throwable) {
                $response = (new Response(''))->setStatus(Response::STATUS_ILLEGAL_PACKAGE)->setMsg($throwable->getMessage());
            }
            $server->send($fd, Protocol::pack((string) $response));
            $server->close($fd);
        });
        foreach ($this->serviceNodes as $node) {
            $this->nodeManager->online($node);
        }
        return $this;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function requireServiceNode(string $serviceName): ServiceNode
    {
        return $this->serviceNodes[$serviceName]
            ?? throw new \InvalidArgumentException(sprintf('Service "%s" is not registered.', $serviceName));
    }
}
