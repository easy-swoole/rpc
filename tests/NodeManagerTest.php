<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Utility\MemoryNodeManager;
use PHPUnit\Framework\TestCase;

final class NodeManagerTest extends TestCase
{
    public function testNodeLifecycle(): void
    {
        $manager = new MemoryNodeManager();
        $node = new ServiceNode('Service', nodeId: 'one');
        self::assertTrue($manager->online($node));
        self::assertSame($node, $manager->getNode('Service'));
        self::assertTrue($manager->hibernate($node));
        self::assertNull($manager->getNode('Service'));
        self::assertTrue($manager->heartbeat($node));
        self::assertSame([$node], $manager->getNodes('Service'));
        self::assertTrue($manager->offline($node));
        self::assertSame([], $manager->getNodes('Service'));
    }
}
