<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use PHPUnit\Framework\TestCase;

final class SwooleTableNodeManagerTest extends TestCase
{
    public function testStoresNodesInSharedTable(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Orders', nodeId: 'shared', ip: '10.0.0.1', port: 9600);
        self::assertTrue($manager->online($node));
        $copy = $manager->getNode('Orders');
        self::assertSame('shared', $copy?->getNodeId());
        self::assertSame('Orders', $copy?->getServiceName());
        self::assertSame('10.0.0.1', $copy?->getIp());
        self::assertSame(9600, $copy?->getPort());
        self::assertTrue($manager->offline($node));
        self::assertNull($manager->getNode('Orders'));
    }
}
