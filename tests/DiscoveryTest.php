<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Server\AssistWorker;
use EasySwoole\Rpc\Server\DiscoveryConfig;
use EasySwoole\Rpc\Server\DiscoveryMessage;
use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use EasySwoole\Rpc\Utility\WeightedRandomNodeSelector;
use PHPUnit\Framework\TestCase;

final class DiscoveryTest extends TestCase
{
    public function testAssistWorkerMaintainsLocalNodeLifecycleWithoutUdpDiscovery(): void
    {
        $node = new ServiceNode('Test', nodeId: 'local-node', ip: '127.0.0.1');
        $manager = new class extends AbstractNodeManager {
            public int $onlineCalls = 0;
            public int $heartbeatCalls = 0;
            public int $offlineCalls = 0;

            public function heartbeat(ServiceNode $node): bool
            {
                ++$this->heartbeatCalls;
                return true;
            }

            public function offline(ServiceNode $node): bool
            {
                ++$this->offlineCalls;
                return true;
            }

            public function online(ServiceNode $node): bool
            {
                ++$this->onlineCalls;
                return true;
            }

            public function getNodes(string $serviceName): array
            {
                return [];
            }
        };
        $worker = new AssistWorker(
            ['Test' => $node],
            $manager,
            5000,
            new DiscoveryConfig(listen: false, broadcast: false),
        );

        (new \ReflectionMethod($worker, 'onlineServices'))->invoke($worker);
        (new \ReflectionMethod($worker, 'heartbeatServices'))->invoke($worker);
        (new \ReflectionMethod($worker, 'onShutDown'))->invoke($worker);

        self::assertSame(1, $manager->onlineCalls);
        self::assertSame(1, $manager->heartbeatCalls);
        self::assertSame(1, $manager->offlineCalls);
    }

    public function testSignedHeartbeatRegistersNodeAndOfflineRemovesIt(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $selector = new WeightedRandomNodeSelector();
        $node = new ServiceNode('Test', nodeId: 'node-one', ip: '127.0.0.1');
        $security = new SecurityConfig(apiKey: 'secret');
        AssistWorker::handlePacket(DiscoveryMessage::encode(DiscoveryMessage::HEARTBEAT, $node, $security), $manager, $security);
        self::assertSame('node-one', $selector->select($manager->getNodes('Test'))?->getNodeId());
        AssistWorker::handlePacket(DiscoveryMessage::encode(DiscoveryMessage::OFFLINE, $node, $security), $manager, $security);
        self::assertNull($selector->select($manager->getNodes('Test')));
    }

    public function testAssistWorkerShutdownOfflinesLocalNode(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'local-node', ip: '127.0.0.1');
        $manager->online($node);
        $process = new AssistWorker(
            ['Test' => $node],
            $manager,
            5000,
            new DiscoveryConfig(listen: false, broadcast: false),
        );

        (new \ReflectionMethod($process, 'onShutDown'))->invoke($process);

        self::assertSame([], $manager->getNodes('Test'));
    }

    public function testRejectsDatagramWithWrongSecret(): void
    {
        $this->expectException(RpcException::class);
        $node = new ServiceNode('Test', nodeId: 'node-one');
        DiscoveryMessage::decode(
            DiscoveryMessage::encode(DiscoveryMessage::HEARTBEAT, $node, new SecurityConfig(apiKey: 'one')),
            new SecurityConfig(apiKey: 'two'),
        );
    }

    public function testRejectsExpiredDiscoveryMessage(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('timestamp');
        $security = new SecurityConfig(apiKey: 'secret', timestampToleranceSeconds: 10);
        $node = new ServiceNode('Test', nodeId: 'node-one');

        DiscoveryMessage::decode(
            DiscoveryMessage::encode(DiscoveryMessage::HEARTBEAT, $node, $security, time() - 11),
            $security,
        );
    }
}
