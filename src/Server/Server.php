<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\AbstractService;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\Protocol;
use Swoole\Server\Port;

class Server
{
    private array $service = [];

    /** @var array<string, ServiceNode> */
    private array $serviceNodes = [];

    private string $nodeId;

    private AbstractNodeManager $nodeManager;

    private ServerAttachStatus $attachStatus = ServerAttachStatus::PENDING;

    private ?AssistWorker $assistWorker = null;

    /** @var null|callable(\Throwable): void */
    private $onException = null;

    public function __construct(
        AbstractNodeManager $nodeManager,
        private readonly ServerConfig $config = new ServerConfig(),
        private readonly DiscoveryConfig $discoveryConfig = new DiscoveryConfig(),
        private readonly SecurityConfig $securityConfig = new SecurityConfig(),
    )
    {
        $this->nodeManager = $nodeManager;
        $this->nodeId = self::uuid();
    }

    public function registerService(AbstractService $service): void
    {
        if ($this->attachStatus !== ServerAttachStatus::PENDING) {
            throw new \LogicException('Services cannot be registered after RPC Server attachment has started.');
        }

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
        $service->__freeze();
        $this->service[$serviceName] = $service;
        $this->serviceNodes[$serviceName] = $node;
    }

    public function getConfig(): ServerConfig
    {
        return $this->config;
    }

    public function hasAttach(): bool
    {
        return $this->attachStatus === ServerAttachStatus::ATTACHED;
    }

    public function getAttachStatus(): ServerAttachStatus
    {
        return $this->attachStatus;
    }

    public function setOnException(?callable $onException): static
    {
        $this->onException = $onException;
        return $this;
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
            return $response->setStatus(ResponseStatus::SERVICE_NOT_EXIST);
        }

        try {
            return $service->__dispatch($request, $response);
        } catch (\Throwable $throwable) {
            $response->setStatus(ResponseStatus::SERVICE_ERROR)->setMsg('Server error.');
            $this->reportException($throwable);
            return $response;
        }
    }

    public function attachServer(\Swoole\Server $server, int $maxPackageSize = 2097152): self
    {
        if ($this->attachStatus === ServerAttachStatus::ATTACHED) {
            throw new \LogicException('RPC Server is already attached.');
        }
        if ($this->attachStatus !== ServerAttachStatus::PENDING) {
            throw new \LogicException(sprintf(
                'RPC Server attachment cannot be attempted again after status %s.',
                $this->attachStatus->name,
            ));
        }
        $this->attachStatus = ServerAttachStatus::ATTACHING;

        try {
            if ($maxPackageSize < 4) {
                throw new \InvalidArgumentException('Maximum package size must be at least 4 bytes.');
            }

            // Prepare: all checks and object construction happen before mutating the Swoole server.
            $this->assertListenerAvailable($server);
            $this->assertUdpListenAvailable();
            $assistWorker = new AssistWorker(
                $this->serviceNodes,
                $this->nodeManager,
                $this->config->heartbeatIntervalMs,
                $this->discoveryConfig,
                $this->securityConfig,
            );

            // Commit: Swoole has no listener/process rollback API. A commit failure is terminal.
            $listener = $this->createListener($server);
            $this->configureListener($listener, $maxPackageSize);
            if ($server->addProcess($assistWorker->getProcess()) === false) {
                throw new \RuntimeException('Unable to attach the RPC assist worker process.');
            }

            $this->assistWorker = $assistWorker;
            $this->attachStatus = ServerAttachStatus::ATTACHED;
        } catch (\Throwable $throwable) {
            $this->attachStatus = ServerAttachStatus::FAILED;
            throw $throwable;
        }

        return $this;
    }

    private function handlePacket(string $packet): Response
    {
        try {
            $request = Request::fromJson(Protocol::unpack($packet), $this->securityConfig);
        } catch (\Throwable) {
            return (new Response(''))
                ->setStatus(ResponseStatus::ILLEGAL_PACKAGE)
                ->setMsg('Illegal RPC request.')
                ->sign($this->securityConfig);
        }

        return $this->handle($request)->sign($this->securityConfig);
    }

    private function assertListenerAvailable(\Swoole\Server $server): void
    {
        foreach ($server->ports as $listener) {
            if ((int) $listener->port === $this->config->listenPort) {
                throw new \LogicException(sprintf(
                    'Port %d is already registered on the Swoole server; RPC will not replace an existing listener.',
                    $this->config->listenPort,
                ));
            }
        }
    }

    private function createListener(\Swoole\Server $server): Port
    {
        $listener = $server->addListener(
            $this->config->listenAddress,
            $this->config->listenPort,
            SWOOLE_SOCK_TCP,
        );
        if (!$listener instanceof Port) {
            throw new \RuntimeException(sprintf(
                'Unable to create RPC listener on %s:%d.',
                $this->config->listenAddress,
                $this->config->listenPort,
            ));
        }
        return $listener;
    }

    private function configureListener(Port $listener, int $maxPackageSize): void
    {
        $listener->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
            'package_max_length' => $maxPackageSize,
        ]);
        if (!$listener->on('receive', function (\Swoole\Server $server, int $fd, int $reactorId, string $packet): void {
            $response = $this->handlePacket($packet);
            $server->send($fd, Protocol::pack((string) $response));
            $server->close($fd);
        })) {
            throw new \RuntimeException('Unable to register the RPC receive callback.');
        }
    }

    private function assertUdpListenAvailable(): void
    {
        if (!$this->discoveryConfig->listen) {
            return;
        }

        $error = null;
        $probe = function () use (&$error): void {
            $socket = new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, 0);
            try {
                if (!$socket->bind($this->discoveryConfig->listenAddress, $this->discoveryConfig->listenPort)) {
                    $error = $socket->errMsg !== '' ? $socket->errMsg : 'unknown socket error';
                }
            } catch (\Throwable $throwable) {
                $error = $throwable->getMessage();
            } finally {
                $socket->close();
            }
        };

        if (\Swoole\Coroutine::getCid() >= 0) {
            $probe();
        } else {
            \Swoole\Coroutine\run($probe);
        }

        if ($error !== null) {
            throw new \RuntimeException(sprintf(
                'Unable to listen on RPC discovery UDP address %s:%d: %s.',
                $this->discoveryConfig->listenAddress,
                $this->discoveryConfig->listenPort,
                $error,
            ));
        }
    }

    private function reportException(\Throwable $throwable): void
    {
        if ($this->onException === null) {
            return;
        }
        try {
            ($this->onException)($throwable);
        } catch (\Throwable) {
            // Exception reporting must never interrupt the RPC response lifecycle.
        }
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
