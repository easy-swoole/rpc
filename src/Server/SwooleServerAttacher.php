<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\Protocol;
use EasySwoole\Rpc\Protocol\ProtocolConfig;
use EasySwoole\Rpc\SecurityConfig;
use Swoole\Server\Port;

final class SwooleServerAttacher
{
    private ServerAttachStatus $status = ServerAttachStatus::PENDING;

    public function __construct(
        private readonly ServerConfig $serverConfig,
        private readonly DiscoveryConfig $discoveryConfig,
        private readonly ProtocolConfig $protocolConfig,
        private readonly SecurityConfig $securityConfig,
        private readonly AbstractNodeManager $nodeManager,
        private readonly ServiceRegistry $registry,
        private readonly ServerRequestHandler $handler,
    ) {}

    public function status(): ServerAttachStatus { return $this->status; }

    public function attach(\Swoole\Server $server): void
    {
        if ($this->status !== ServerAttachStatus::PENDING) {
            throw new RpcException('RPC Server attachment can only be attempted once.');
        }
        $this->status = ServerAttachStatus::ATTACHING;
        $this->registry->freeze();
        try {
            foreach ($server->ports as $port) {
                if ((int) $port->port === $this->serverConfig->listenPort) {
                    throw new RpcException(sprintf(
                        'Port %d is already registered on the Swoole server.',
                        $this->serverConfig->listenPort,
                    ));
                }
            }
            $this->assertUdpAvailable();
            $worker = new AssistWorker($this->registry->nodes(), $this->nodeManager, $this->serverConfig->heartbeatIntervalMs, $this->discoveryConfig, $this->securityConfig);
            $listener = $server->addListener($this->serverConfig->listenAddress, $this->serverConfig->listenPort, SWOOLE_SOCK_TCP);
            if (!$listener instanceof Port) {
                throw new RpcException('Unable to create RPC listener.');
            }
            $listener->set([
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => $this->protocolConfig->maxPackageSize,
            ]);
            if (!$listener->on('receive', function (\Swoole\Server $server, int $fd, int $reactorId, string $packet): void {
                $server->send($fd, Protocol::pack($this->handler->handlePacket(Protocol::unpack($packet))));
                $server->close($fd);
            })) {
                throw new RpcException('Unable to register the RPC receive callback.');
            }
            if ($server->addProcess($worker->getProcess()) === false) {
                throw new RpcException('Unable to attach the RPC assist worker process.');
            }
            $this->status = ServerAttachStatus::ATTACHED;
        } catch (\Throwable $throwable) {
            $this->status = ServerAttachStatus::FAILED;
            throw $throwable;
        }
    }
    private function assertUdpAvailable(): void
    {
        if (!$this->discoveryConfig->listen) {
            return;
        }
        $error = null;
        $probe = function () use (&$error): void {
            $socket = new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, 0);
            try {
                if (!$socket->bind($this->discoveryConfig->listenAddress, $this->discoveryConfig->listenPort)) {
                    $error = $socket->errMsg ?: 'unknown socket error';
                }
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
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
            throw new RpcException(sprintf(
                'Unable to listen on RPC discovery UDP address %s:%d: %s.',
                $this->discoveryConfig->listenAddress,
                $this->discoveryConfig->listenPort,
                $error,
            ));
        }
    }
}
