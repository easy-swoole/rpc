<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Process\Config as ProcessConfig;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use Swoole\Coroutine\Socket;

final class AssistWorker extends AbstractProcess
{
    private ?Socket $socket = null;

    private ?int $heartbeatTimerId = null;

    private bool $running = false;

    /** @param array<string, ServiceNode> $serviceNodes */
    public function __construct(
        private readonly array $serviceNodes,
        private readonly AbstractNodeManager $nodeManager,
        private readonly DiscoveryConfig $config,
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

        if ($this->config->listen || $this->config->broadcast) {
            $this->socket = new Socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
            $this->socket->setOption(SOL_SOCKET, SO_BROADCAST, true);
            if ($this->config->listen && !$this->socket->bind($this->config->listenAddress, $this->config->listenPort)) {
                throw new \RuntimeException('Unable to bind RPC discovery UDP socket.');
            }
        }
        $this->running = true;

        $this->broadcast();
        $this->heartbeatTimerId = $this->addTick($this->config->heartbeatIntervalMs, function (): void {
            $this->heartbeatServices();
            if ($this->nodeManager instanceof SwooleTableNodeManager) {
                $this->nodeManager->prune($this->config->nodeTtlSeconds);
            }
            $this->broadcast();
        });

        if ($this->config->listen) {
            while ($this->running) {
                $peer = null;
                $packet = $this->socket->recvfrom($peer);
                if (is_string($packet) && $packet !== '') {
                    try {
                        self::handlePacket($packet, $this->nodeManager, $this->config->secret);
                    } catch (\Throwable) {
                        // Invalid or untrusted discovery datagrams are deliberately ignored.
                    }
                }
            }
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
        $this->socket?->close();
        $this->socket = null;
    }

    public static function handlePacket(string $packet, AbstractNodeManager $manager, ?string $secret = null): void
    {
        [$action, $node] = DiscoveryMessage::decode($packet, $secret);
        if ($action === DiscoveryMessage::OFFLINE) {
            $manager->offline($node);
            return;
        }
        $manager->heartbeat($node);
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
        if (!$this->config->broadcast || $this->socket === null) {
            return;
        }
        foreach ($this->serviceNodes as $node) {
            $packet = DiscoveryMessage::encode($action, $node, $this->config->secret);
            foreach ($this->config->broadcastAddresses as $address) {
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
