<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Process\Config as ProcessConfig;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use Swoole\Coroutine\Socket;

final class AssistWorker extends AbstractProcess
{
    private ?Socket $socket = null;
    private ?int $heartbeatTimerId = null;
    private ?int $discoveryTimerId = null;
    private bool $running = false;

    /** @param array<string, ServiceNode> $serviceNodes */
    public function __construct(
        private readonly array $serviceNodes,
        private readonly AbstractNodeManager $nodeManager,
        private readonly int $heartbeatIntervalMs,
        private readonly DiscoveryConfig $discoveryConfig,
        private readonly SecurityConfig $securityConfig = new SecurityConfig(),
    ) {
        $processConfig = new ProcessConfig();
        $processConfig->setProcessName('rpc-assist-worker');
        $processConfig->setProcessGroup('rpc');
        $processConfig->setPipeType(ProcessConfig::PIPE_TYPE_NONE);
        $processConfig->setEnableCoroutine(true);
        parent::__construct($processConfig);
    }

    protected function run($arg): void
    {
        $this->onlineServices();
        $this->heartbeatTimerId = $this->addTick(
            $this->heartbeatIntervalMs,
            fn () => $this->heartbeatServices(),
        );

        if (!$this->udpEnabled()) {
            return;
        }

        $this->startUdpDiscovery();
        if ($this->discoveryConfig->broadcast) {
            $this->broadcast();
            $this->discoveryTimerId = $this->addTick(
                $this->discoveryConfig->broadcastIntervalMs,
                fn () => $this->broadcast(),
            );
        }

        if ($this->discoveryConfig->listen) {
            $this->receiveDiscoveryPackets();
        }
    }

    protected function onShutDown(): void
    {
        $this->running = false;
        $this->broadcast(DiscoveryMessage::OFFLINE);
        $this->offlineServices();
        if ($this->heartbeatTimerId !== null) {
            $this->clearTick($this->heartbeatTimerId);
            $this->heartbeatTimerId = null;
        }
        if ($this->discoveryTimerId !== null) {
            $this->clearTick($this->discoveryTimerId);
            $this->discoveryTimerId = null;
        }
        $this->socket?->close();
        $this->socket = null;
    }

    public static function handlePacket(
        string $packet,
        AbstractNodeManager $manager,
        SecurityConfig $securityConfig = new SecurityConfig(),
    ): void
    {
        [$action, $node] = DiscoveryMessage::decode($packet, $securityConfig);
        if ($action === DiscoveryMessage::OFFLINE) {
            $manager->offline($node);
            return;
        }
        $manager->heartbeat($node);
    }

    private function udpEnabled(): bool
    {
        return $this->discoveryConfig->listen || $this->discoveryConfig->broadcast;
    }

    private function startUdpDiscovery(): void
    {
        $this->socket = new Socket(AF_INET, SOCK_DGRAM, 0);
        $this->socket->setOption(SOL_SOCKET, SO_BROADCAST, true);
        if ($this->discoveryConfig->listen
            && !$this->socket->bind($this->discoveryConfig->listenAddress, $this->discoveryConfig->listenPort)) {
            throw new \RuntimeException('Unable to bind RPC discovery UDP socket.');
        }
        $this->running = true;
    }

    private function receiveDiscoveryPackets(): void
    {
        while ($this->running) {
            $peer = null;
            $packet = $this->socket?->recvfrom($peer);
            if (is_string($packet) && $packet !== '') {
                try {
                    self::handlePacket($packet, $this->nodeManager, $this->securityConfig);
                } catch (\Throwable) {
                    // Invalid or untrusted discovery datagrams are deliberately ignored.
                }
            }
        }
    }

    private static function splitAddress(string $address): array
    {
        $position = strrpos($address, ':');
        if ($position === false) {
            throw new \InvalidArgumentException('Discovery address must use host:port format.');
        }
        return [substr($address, 0, $position), (int) substr($address, $position + 1)];
    }

    private function broadcast(string $action = DiscoveryMessage::HEARTBEAT): void
    {
        if (!$this->discoveryConfig->broadcast || $this->socket === null) {
            return;
        }
        foreach ($this->serviceNodes as $node) {
            $packet = DiscoveryMessage::encode($action, $node, $this->securityConfig);
            foreach ($this->discoveryConfig->broadcastAddresses as $address) {
                [$host, $port] = self::splitAddress($address);
                $this->socket->sendto($host, $port, $packet);
            }
        }
    }

    private function onlineServices(): void
    {
        foreach ($this->serviceNodes as $node) {
            $this->nodeManager->online($node);
        }
    }

    private function heartbeatServices(): void
    {
        foreach ($this->serviceNodes as $node) {
            $this->nodeManager->heartbeat($node);
        }
    }

    private function offlineServices(): void
    {
        foreach ($this->serviceNodes as $node) {
            $this->nodeManager->offline($node);
        }
    }
}
