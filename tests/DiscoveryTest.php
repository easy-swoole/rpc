<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Server\AssistWorker;
use EasySwoole\Rpc\Server\DiscoveryMessage;
use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Utility\MemoryNodeManager;
use PHPUnit\Framework\TestCase;

final class DiscoveryTest extends TestCase
{
    public function testSignedHeartbeatRegistersNodeAndOfflineRemovesIt(): void
    {
        $manager = new MemoryNodeManager();
        $node = new ServiceNode('Test', nodeId: 'node-one', ip: '127.0.0.1');
        AssistWorker::handlePacket(DiscoveryMessage::encode(DiscoveryMessage::HEARTBEAT, $node, 'secret'), $manager, 'secret');
        self::assertSame('node-one', $manager->getNode('Test')?->getNodeId());
        AssistWorker::handlePacket(DiscoveryMessage::encode(DiscoveryMessage::OFFLINE, $node, 'secret'), $manager, 'secret');
        self::assertNull($manager->getNode('Test'));
    }

    public function testRejectsDatagramWithWrongSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $node = new ServiceNode('Test');
        DiscoveryMessage::decode(DiscoveryMessage::encode(DiscoveryMessage::HEARTBEAT, $node, 'one'), 'two');
    }
}
