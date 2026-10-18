<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use EasySwoole\Rpc\Utility\WeightedRandomNodeSelector;
use PHPUnit\Framework\TestCase;

final class SwooleTableNodeManagerTest extends TestCase
{
    public function testStoresNodesInSharedTable(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $selector = new WeightedRandomNodeSelector();
        $node = new ServiceNode('Orders', nodeId: 'shared', ip: '10.0.0.1', port: 9600);
        self::assertTrue($manager->online($node));
        $copy = $selector->select($manager->getNodes('Orders'));
        self::assertSame('shared', $copy?->getNodeId());
        self::assertSame('Orders', $copy?->getServiceName());
        self::assertSame('10.0.0.1', $copy?->getIp());
        self::assertSame(9600, $copy?->getPort());
        self::assertTrue($manager->offline($node));
        self::assertNull($selector->select($manager->getNodes('Orders')));
    }

    public function testGetNodesFiltersAndRemovesExpiredNodes(): void
    {
        $table = new \Swoole\Table(16);
        $table->column('service', \Swoole\Table::TYPE_STRING, 128);
        $table->column('node', \Swoole\Table::TYPE_STRING, 2048);
        $table->column('heartbeat', \Swoole\Table::TYPE_INT, 8);
        $table->create();

        $manager = new SwooleTableNodeManager(table: $table, nodeTtlSeconds: 15);
        $node = new ServiceNode('Orders', nodeId: 'expired');
        $manager->online($node);
        $key = sha1("Orders\0expired");
        $table->set($key, ['heartbeat' => time() - 16]);

        self::assertSame([], $manager->getNodes('Orders'));
        self::assertFalse($table->exist($key));
    }

    public function testRejectsInvalidNodeTtl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SwooleTableNodeManager(nodeTtlSeconds: 0);
    }
}
