<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;

final class AssistWorker
{
    private ?\Swoole\Process $process = null;

    /** @param array<string, ServiceNode> $serviceNodes */
    public function __construct(
        private readonly array $serviceNodes,
        private readonly AbstractNodeManager $nodeManager,
        private readonly DiscoveryConfig $config,
    ) {}

    public function process(): \Swoole\Process
    {
        return $this->process ??= new \Swoole\Process(function (): void {
            $socket = new \Swoole\Coroutine\Socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
            $socket->setOption(SOL_SOCKET, SO_BROADCAST, true);
            if ($this->config->listen && !$socket->bind($this->config->listenAddress, $this->config->listenPort)) {
                throw new \RuntimeException('Unable to bind RPC discovery UDP socket.');
            }

            $broadcast = function (string $action = DiscoveryMessage::HEARTBEAT) use ($socket): void {
                if (!$this->config->broadcast) {
                    return;
                }
                foreach ($this->serviceNodes as $node) {
                    $packet = DiscoveryMessage::encode($action, $node, $this->config->secret);
                    foreach ($this->config->broadcastAddresses as $address) {
                        [$host, $port] = self::splitAddress($address);
                        $socket->sendto($host, $port, $packet);
                    }
                }
            };

            \Swoole\Process::signal(SIGTERM, function () use ($broadcast, $socket): void {
                $broadcast(DiscoveryMessage::OFFLINE);
                \Swoole\Timer::clearAll();
                $socket->close();
                exit(0);
            });

            $broadcast();
            \Swoole\Timer::tick($this->config->heartbeatIntervalMs, function () use ($broadcast): void {
                foreach ($this->serviceNodes as $node) {
                    $this->nodeManager->heartbeat($node);
                }
                if ($this->nodeManager instanceof SwooleTableNodeManager) {
                    $this->nodeManager->prune($this->config->nodeTtlSeconds);
                }
                $broadcast();
            });

            if ($this->config->listen) {
                while (true) {
                    $peer = null;
                    $packet = $socket->recvfrom($peer);
                    if (is_string($packet) && $packet !== '') {
                        try {
                            self::handlePacket($packet, $this->nodeManager, $this->config->secret);
                        } catch (\Throwable) {
                            // Invalid or untrusted discovery datagrams are deliberately ignored.
                        }
                    }
                }
            }
        }, false, SOCK_DGRAM, true);
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
}
