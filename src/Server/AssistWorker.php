<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Config\SecurityConfig;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Process\Config as ProcessConfig;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use Swoole\Coroutine\Socket;

final class AssistWorker extends AbstractProcess
{
    private mixed $onRuntimeError;
    private ?Socket $socket = null;
    private ?int $heartbeatTimerId = null;
    private ?int $discoveryTimerId = null;
    private ?int $udpRetryTimerId = null;
    private bool $published = false;
    private bool $running = false;
    private bool $shuttingDown = false;

    /** @param array<string, ServiceNode> $serviceNodes */
    public function __construct(
        private readonly array $serviceNodes,
        private readonly AbstractNodeManager $nodeManager,
        private readonly AssistWorkerConfig $config = new AssistWorkerConfig(),
        ?callable $onRuntimeError = null,
        private readonly ?TcpProcessHeartbeats $tcpHeartbeats = null,
    ) {
        $this->onRuntimeError = $onRuntimeError;
        $processConfig = new ProcessConfig();
        $processName = 'rpc-assist-worker';
        if (($nodeName = trim($this->config->nodeName)) !== '') {
            $processName .= '.' . $nodeName;
        }
        $processConfig->setProcessName($processName);
        $processConfig->setProcessGroup('rpc');
        $processConfig->setPipeType(ProcessConfig::PIPE_TYPE_NONE);
        $processConfig->setEnableCoroutine(true);
        parent::__construct($processConfig);
    }

    protected function run($arg): void
    {
        // TCP 子进程可能尚未开始监听，先检查共享表，再定期检查上下线变化。
        $this->refreshAvailability();
        if ($this->tcpHeartbeats !== null) {
            $this->addTick(TcpProcessHeartbeats::INTERVAL_MS, fn () => $this->refreshAvailability());
        }
        $this->heartbeatTimerId = $this->addTick(
            $this->config->heartbeatIntervalMs,
            function (): void {
                if ($this->published && $this->tcpAvailable()) {
                    $this->heartbeatServices();
                }
            },
        );

        if ($this->config->discovery->listen || $this->config->discovery->broadcast) {
            $this->startDiscoveryLifecycle();
        }
    }

    protected function onShutDown(): void
    {
        $this->shuttingDown = true;
        // 先通知其他实例下线，再删除本机记录；收不到广播时仍由 NodeManager TTL 兜底。
        $this->withdrawServices();
        if ($this->heartbeatTimerId !== null) {
            $this->clearTick($this->heartbeatTimerId);
            $this->heartbeatTimerId = null;
        }
        $this->cancelDiscoveryRetry();
        $this->stopDiscovery();
    }

    public static function handlePacket(
        string $packet,
        AbstractNodeManager $manager,
        SecurityConfig $securityConfig = new SecurityConfig(),
    ): bool
    {
        [$action, $node] = DiscoveryMessage::decode($packet, $securityConfig);
        if ($action === DiscoveryMessage::OFFLINE) {
            return $manager->offline($node);
        }
        return $manager->heartbeat($node);
    }

    private function startUdpDiscovery(): bool
    {
        try {
            $this->socket = new Socket(AF_INET, SOCK_DGRAM, 0);
        } catch (\Throwable $throwable) {
            $this->report('Unable to create RPC discovery UDP socket: ' . $throwable->getMessage());
            $this->socket = null;
            return false;
        }
        if ($this->config->discovery->broadcast
            && !$this->socket->setOption(SOL_SOCKET, SO_BROADCAST, true)) {
            $this->report($this->socket->errMsg ?: 'Unable to enable RPC UDP broadcast.');
            $this->socket->close();
            $this->socket = null;
            return false;
        }
        if ($this->config->discovery->listen
            && !$this->socket->bind($this->config->discovery->listenAddress, $this->config->discovery->listenPort)) {
            $this->report(sprintf(
                'RPC discovery UDP listen failed on %s:%d: %s',
                $this->config->discovery->listenAddress,
                $this->config->discovery->listenPort,
                $this->socket->errMsg ?: 'unknown socket error',
            ));
            $this->socket->close();
            $this->socket = null;
            return false;
        }
        // 同一个 UDP Socket 同时承担监听与广播，减少进程内无意义的资源拆分。
        $this->running = true;
        return true;
    }

    private function startDiscoveryLifecycle(): void
    {
        if ($this->shuttingDown) {
            return;
        }
        if (!$this->startUdpDiscovery()) {
            // 创建或 bind 失败才重试；已绑定 Socket 不会因外部网络暂时中断而重建。
            $this->scheduleUdpRetry();
            return;
        }
        $this->cancelDiscoveryRetry();
        if ($this->config->discovery->broadcast) {
            $this->broadcast();
            $this->discoveryTimerId = $this->addTick(
                $this->config->discovery->broadcastIntervalMs,
                fn () => $this->broadcast(),
            );
        }
        if ($this->config->discovery->listen) {
            \Swoole\Coroutine::create(fn () => $this->receiveDiscoveryPackets());
        }
    }

    private function scheduleUdpRetry(): void
    {
        if ($this->shuttingDown || $this->udpRetryTimerId !== null) {
            return;
        }
        $this->udpRetryTimerId = $this->addTick(
            $this->config->discovery->socketRetryIntervalMs,
            fn () => $this->startDiscoveryLifecycle(),
        );
    }

    private function receiveDiscoveryPackets(): void
    {
        while ($this->running) {
            $peer = null;
            $packet = $this->socket?->recvfrom($peer);
            if ($packet === false) {
                if ($this->shuttingDown || !$this->running) {
                    return;
                }
                // 让出协程，避免 socket 持续返回错误时占满 worker。
                \Swoole\Coroutine::sleep(0.01);
                continue;
            }
            if (is_string($packet) && $packet !== '') {
                try {
                    [$action, $node] = DiscoveryMessage::decode($packet, $this->config->security);
                } catch (\Throwable) {
                    // 非法或签名不可信的 UDP 数据直接忽略，避免外部垃圾包污染运行错误日志。
                    continue;
                }
                $this->handleDiscoveredNode($action, $node);
            }
        }
    }

    private function broadcast(string $action = DiscoveryMessage::HEARTBEAT): void
    {
        if (!$this->config->discovery->broadcast || $this->socket === null) {
            return;
        }
        if ($action === DiscoveryMessage::HEARTBEAT && (!$this->published || !$this->tcpAvailable())) {
            return;
        }
        foreach ($this->serviceNodes as $node) {
            try {
                $packet = DiscoveryMessage::encode($action, $node, $this->config->security);
            } catch (\Throwable $throwable) {
                $this->report(sprintf(
                    'RPC discovery packet encoding failed for service "%s", node "%s": %s',
                    $node->getServiceName(),
                    $node->getNodeId(),
                    $throwable->getMessage(),
                ));
                continue;
            }
            foreach ($this->config->discovery->broadcastTargets() as [$host, $port]) {
                if (!$this->socket->sendto($host, $port, $packet)) {
                    $this->report(sprintf(
                        'RPC discovery broadcast failed for service "%s" node "%s" to %s:%d: %s',
                        $node->getServiceName(),
                        $node->getNodeId(),
                        $host,
                        $port,
                        $this->socket->errMsg ?: 'unknown socket error',
                    ));
                }
            }
        }
    }

    private function tcpAvailable(): bool
    {
        return $this->tcpHeartbeats === null || $this->tcpHeartbeats->hasLiveProcess();
    }

    private function refreshAvailability(): void
    {
        if ($this->shuttingDown) {
            return;
        }
        $available = $this->tcpAvailable();
        if ($available === $this->published) {
            return;
        }
        if (!$available) {
            $this->withdrawServices();
            return;
        }

        // 多个 TCP 进程共用一个端口，对外只发布一个服务节点。
        $this->published = true;
        $this->onlineServices();
        $this->broadcast();
    }

    private function withdrawServices(): void
    {
        $this->published = false;
        $this->broadcast(DiscoveryMessage::OFFLINE);
        $this->offlineServices();
    }

    private function onlineServices(): void
    {
        foreach ($this->serviceNodes as $node) {
            $this->updateNode($node, 'online', fn (): bool => $this->nodeManager->online($node));
        }
    }

    private function handleDiscoveredNode(string $action, ServiceNode $node): void
    {
        $operationName = $action === DiscoveryMessage::OFFLINE ? 'offline' : 'heartbeat';
        $this->updateNode(
            $node,
            $operationName,
            fn (): bool => $action === DiscoveryMessage::OFFLINE
                ? $this->nodeManager->offline($node)
                : $this->nodeManager->heartbeat($node),
        );
    }

    private function heartbeatServices(): void
    {
        foreach ($this->serviceNodes as $node) {
            $this->updateNode($node, 'heartbeat', fn (): bool => $this->nodeManager->heartbeat($node));
        }
    }

    private function offlineServices(): void
    {
        foreach ($this->serviceNodes as $node) {
            $this->updateNode($node, 'offline', fn (): bool => $this->nodeManager->offline($node));
        }
    }

    private function updateNode(ServiceNode $node, string $operationName, callable $operation): void
    {
        // NodeManager 的 false 是可恢复的运行失败，只上报日志，不终止后续心跳。
        try {
            if (!$operation()) {
                $this->report($this->nodeErrorMessage($node, $operationName));
            }
        } catch (\Throwable $throwable) {
            $this->report($this->nodeErrorMessage($node, $operationName) . ': ' . $throwable->getMessage());
        }
    }

    private function nodeErrorMessage(ServiceNode $node, string $operationName): string
    {
        return sprintf(
            'RPC node %s failed for service "%s", node "%s".',
            $operationName,
            $node->getServiceName(),
            $node->getNodeId(),
        );
    }

    private function report(string $message): void
    {
        if (!is_callable($this->onRuntimeError)) {
            return;
        }
        try {
            ($this->onRuntimeError)($message);
        } catch (\Throwable) {
            // 日志回调异常不能终止节点心跳或 UDP 服务发现。
        }
    }

    private function cancelDiscoveryRetry(): void
    {
        if ($this->udpRetryTimerId === null) {
            return;
        }
        $this->clearTick($this->udpRetryTimerId);
        $this->udpRetryTimerId = null;
    }

    private function stopDiscovery(): void
    {
        $this->running = false;
        if ($this->discoveryTimerId !== null) {
            $this->clearTick($this->discoveryTimerId);
            $this->discoveryTimerId = null;
        }
        $this->socket?->close();
        $this->socket = null;
    }
}
