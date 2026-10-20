<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Exception\RpcException;

use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use EasySwoole\Rpc\Utility\WeightedRandomNodeSelector;
use PHPUnit\Framework\TestCase;

final class SwooleTableNodeManagerTest extends TestCase
{
    public function testExposesNodeTtlForServerIntervalValidation(): void
    {
        self::assertSame(30, (new SwooleTableNodeManager(nodeTtlSeconds: 30))->nodeTtlSeconds());
    }

    public function testStoresNodesInSharedTable(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $selector = new WeightedRandomNodeSelector();
        $node = new ServiceNode('Orders', nodeId: 'shared', host: '10.0.0.1', port: 9600);
        self::assertTrue($manager->online($node));
        $copy = $selector->select($manager->getNodes('Orders'));
        self::assertSame('shared', $copy?->getNodeId());
        self::assertSame('Orders', $copy?->getServiceName());
        self::assertSame('10.0.0.1', $copy?->getHost());
        self::assertSame(9600, $copy?->getPort());
        self::assertTrue($manager->offline($node));
        self::assertNull($selector->select($manager->getNodes('Orders')));
        self::assertFalse($manager->offline($node));
    }

    public function testGetNodesFiltersAndRemovesExpiredNodes(): void
    {
        $table = new \Swoole\Table(16);
        $table->column('service', \Swoole\Table::TYPE_STRING, SwooleTableNodeManager::SERVICE_COLUMN_BYTES);
        $table->column('node', \Swoole\Table::TYPE_STRING, SwooleTableNodeManager::NODE_COLUMN_BYTES);
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

    public function testRejectsRowsThatWouldBeTruncated(): void
    {
        $manager = new SwooleTableNodeManager(16);

        self::assertFalse($manager->online(new ServiceNode(str_repeat('s', 65), nodeId: 'node')));
        self::assertFalse($manager->online(new ServiceNode('Orders', nodeId: 'node', nodeName: str_repeat('n', 4096))));
        self::assertSame([], $manager->getNodes('Orders'));
    }

    public function testGetNodesDeletesDamagedRowsAndKeepsHealthyNodes(): void
    {
        $table = new \Swoole\Table(16);
        $table->column('service', \Swoole\Table::TYPE_STRING, SwooleTableNodeManager::SERVICE_COLUMN_BYTES);
        $table->column('node', \Swoole\Table::TYPE_STRING, SwooleTableNodeManager::NODE_COLUMN_BYTES);
        $table->column('heartbeat', \Swoole\Table::TYPE_INT, 8);
        $table->create();
        $manager = new SwooleTableNodeManager(table: $table);
        $healthy = new ServiceNode('Orders', nodeId: 'healthy');
        self::assertTrue($manager->online($healthy));
        $damagedKey = sha1("Orders\0damaged");
        self::assertTrue($table->set($damagedKey, [
            'service' => 'Orders',
            'node' => '{invalid json',
            'heartbeat' => time(),
        ]));

        $nodes = $manager->getNodes('Orders');

        self::assertCount(1, $nodes);
        self::assertSame('healthy', $nodes[0]->getNodeId());
        self::assertFalse($table->exist($damagedKey));
    }

    public function testRejectsInvalidNodeTtl(): void
    {
        $this->expectException(RpcException::class);
        new SwooleTableNodeManager(nodeTtlSeconds: 0);
    }
}
