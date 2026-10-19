<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Rpc\RuntimeErrorReporter;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Process\Config as ProcessConfig;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use Swoole\Coroutine\Socket;

final class AssistWorker extends AbstractProcess
{
    private ?Socket $socket = null;
    private ?int $heartbeatTimerId = null;
    private ?int $discoveryTimerId = null;
    private ?int $udpRetryTimerId = null;
    private bool $running = false;
    private bool $shuttingDown = false;

    /** @param array<string, ServiceNode> $serviceNodes */
    public function __construct(
        private readonly array $serviceNodes,
        private readonly AbstractNodeManager $nodeManager,
        private readonly AssistWorkerConfig $config = new AssistWorkerConfig(),
        private readonly RuntimeErrorReporter $runtimeErrorReporter = new RuntimeErrorReporter(),
    ) {
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
        // AssistWorker 启动即登记本机服务，并持续刷新本机 NodeManager 中的心跳。
        $this->onlineServices();
        $this->heartbeatTimerId = $this->addTick(
            $this->config->heartbeatIntervalMs,
            fn () => $this->heartbeatServices(),
        );

        if (!$this->udpEnabled()) {
            return;
        }

        $this->startDiscoveryLifecycle();
    }

    protected function onShutDown(): void
    {
        $this->shuttingDown = true;
        // 先通知其他实例下线，再删除本机记录；收不到广播时仍由 NodeManager TTL 兜底。
        $this->broadcast(DiscoveryMessage::OFFLINE);
        $this->offlineServices();
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

    private function udpEnabled(): bool
    {
        return $this->config->discovery->listen || $this->config->discovery->broadcast;
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
                // recvfrom() 返回 false 通常表示 Socket 或进程正在关闭，继续重建没有意义。
                if (!$this->shuttingDown) {
                    $this->report($this->socket?->errMsg ?: 'RPC discovery receive loop stopped unexpectedly.');
                }
                $this->stopDiscovery();
                return;
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
        $this->runtimeErrorReporter->report($message);
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
