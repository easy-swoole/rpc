<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\AbstractService;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\Protocol\ProtocolCodec;
use EasySwoole\Rpc\Protocol\ProtocolConfig;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Rpc\RuntimeErrorReporter;

final class Server
{
    private readonly ServiceRegistry $registry;
    private readonly ServerRequestHandler $handler;
    private readonly SwooleServerAttacher $attacher;

    public function __construct(
        AbstractNodeManager $nodeManager,
        private readonly ServerConfig $config = new ServerConfig(),
        DiscoveryConfig $discoveryConfig = new DiscoveryConfig(),
        SecurityConfig $securityConfig = new SecurityConfig(),
        ProtocolConfig $protocolConfig = new ProtocolConfig(),
        RuntimeErrorReporter $runtimeErrorReporter = new RuntimeErrorReporter(),
    ) {
        $this->registry = new ServiceRegistry($nodeManager, $config, self::uuid());
        $this->handler = new ServerRequestHandler(
            $this->registry,
            new ProtocolCodec(new PayloadSigner($securityConfig)),
        );
        $this->attacher = new SwooleServerAttacher(
            $config,
            $discoveryConfig,
            $protocolConfig,
            $securityConfig,
            $nodeManager,
            $this->registry,
            $this->handler,
            $runtimeErrorReporter,
        );
    }

    public function registerService(AbstractService $service): void { $this->registry->register($service); }
    public function getConfig(): ServerConfig { return $this->config; }
    public function hasAttach(): bool { return $this->attacher->status() === ServerAttachStatus::ATTACHED; }
    public function getAttachStatus(): ServerAttachStatus { return $this->attacher->status(); }
    public function getServiceNode(string $name): ?ServiceNode { return $this->registry->node($name); }
    /** @return array<string, ServiceNode> */
    public function allServiceNodes(): array { return $this->registry->nodes(); }
    public function onlineService(string $name): bool { return $this->registry->online($name); }
    public function heartbeatService(string $name): bool { return $this->registry->heartbeat($name); }
    public function offlineService(string $name): bool { return $this->registry->offline($name); }
    public function handle(Request $request): Response { return $this->handler->handle($request); }

    public function setOnException(?callable $callback): static
    {
        $this->handler->setOnException($callback);
        return $this;
    }

    /** @internal Configure background-process error reporting through Rpc. */
    public function setOnRuntimeError(?callable $callback): static
    {
        $this->attacher->setOnRuntimeError($callback);
        return $this;
    }

    public function attachServer(\Swoole\Server $server): self
    {
        $this->attacher->attach($server);
        return $this;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
