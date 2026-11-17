<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Config\ServerConfig;
use EasySwoole\Rpc\Config\DiscoveryConfig;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\Config\ProtocolConfig;
use EasySwoole\Rpc\Config\SecurityConfig;
use EasySwoole\Component\Process\Socket\TcpProcessConfig;

final class SwooleServerAttacher
{
    private ServerAttachStatus $status = ServerAttachStatus::PENDING;
    private mixed $onRuntimeError;

    public function __construct(
        private readonly ServerConfig $serverConfig,
        private readonly DiscoveryConfig $discoveryConfig,
        private readonly ProtocolConfig $protocolConfig,
        private readonly SecurityConfig $securityConfig,
        private readonly AbstractNodeManager $nodeManager,
        private readonly ServiceRegistry $registry,
        private readonly ServerRequestHandler $handler,
        ?callable $onRuntimeError = null,
    ) {
        $this->onRuntimeError = $onRuntimeError;
    }

    public function status(): ServerAttachStatus { return $this->status; }

    public function setOnRuntimeError(callable $callback): void
    {
        if ($this->status !== ServerAttachStatus::PENDING) {
            throw new RpcException('Runtime error handler must be configured before RPC Server attachment.');
        }
        $this->onRuntimeError = $callback;
    }

    public function attach(\Swoole\Server $server): void
    {
        if ($this->status !== ServerAttachStatus::PENDING) {
            throw new RpcException('RPC Server attachment can only be attempted once.');
        }
        $this->status = ServerAttachStatus::ATTACHING;
        // 从这一刻起禁止再注册服务，保证 TCP 进程与 AssistWorker 使用同一份节点快照。
        $this->registry->freeze();
        try {
            $this->assertNodeTtlIntervals();
            $nodes = $this->registry->nodes();
            if ($nodes !== []) {
                // 已存在的端口可能由业务方配置了其他协议，不能擅自复用为 RPC listener。
                foreach ($server->ports as $port) {
                    if ((int) $port->port === $this->serverConfig->listenPort) {
                        throw new RpcException(sprintf(
                            'Port %d is already registered on the Swoole server.',
                            $this->serverConfig->listenPort,
                        ));
                    }
                }
            }
            // 在挂载进程之前完成所有可预检项目，减少半挂载状态。
            $this->assertUdpAvailable();
            $heartbeats = $nodes === [] ? null : new TcpProcessHeartbeats($this->serverConfig->tcpProcessCount);
            if ($heartbeats !== null) {
                $signer = new PayloadSigner($this->securityConfig);
                for ($index = 0; $index < $this->serverConfig->tcpProcessCount; $index++) {
                    $config = new TcpProcessConfig();
                    $config->setProcessName('rpc-tcp-' . $index);
                    $config->setProcessGroup('rpc');
                    $config->setPipeType(TcpProcessConfig::PIPE_TYPE_NONE);
                    $config->setEnableCoroutine(true);
                    $config->setListenAddress($this->serverConfig->listenAddress);
                    $config->setListenPort($this->serverConfig->listenPort);
                    $tcpProcess = new RpcTcpProcess($config, $index, $heartbeats, $this->protocolConfig, $signer, $this->handler);
                    $server->addProcess($tcpProcess->getProcess());
                }
            }
            $worker = new AssistWorker(
                $nodes,
                $this->nodeManager,
                AssistWorkerConfig::fromServer($this->serverConfig, $this->discoveryConfig, $this->securityConfig),
                $this->onRuntimeError,
                $heartbeats,
            );
            $server->addProcess($worker->getProcess());
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
        // attach 阶段临时 bind 探测端口，避免 AssistWorker fork 后才因端口冲突反复退出。
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

    private function assertNodeTtlIntervals(): void
    {
        $ttlSeconds = $this->nodeManager->nodeTtlSeconds();
        if ($ttlSeconds === null) {
            return;
        }
        $ttlMilliseconds = $ttlSeconds > intdiv(PHP_INT_MAX, 1000)
            ? PHP_INT_MAX
            : $ttlSeconds * 1000;
        if ($this->serverConfig->heartbeatIntervalMs >= $ttlMilliseconds) {
            throw new RpcException(sprintf(
                'Node TTL (%d ms) must be greater than the heartbeat interval (%d ms).',
                $ttlMilliseconds,
                $this->serverConfig->heartbeatIntervalMs,
            ));
        }
        if ($this->discoveryConfig->broadcast
            && $this->discoveryConfig->broadcastIntervalMs >= $ttlMilliseconds) {
            throw new RpcException(sprintf(
                'Node TTL (%d ms) must be greater than the discovery broadcast interval (%d ms).',
                $ttlMilliseconds,
                $this->discoveryConfig->broadcastIntervalMs,
            ));
        }
    }

}
