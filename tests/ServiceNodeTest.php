<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Exception\RpcException;

use EasySwoole\Rpc\Server\ServiceNode;
use PHPUnit\Framework\TestCase;

final class ServiceNodeTest extends TestCase
{
    public function testNodeIdentitySurvivesSerialization(): void
    {
        $node = new ServiceNode('User', nodeId: 'generated', ip: '127.0.0.1', port: 9601, nodeWeight: 20);
        $copy = ServiceNode::fromArray($node->jsonSerialize());
        self::assertNotSame($node, $copy);
        self::assertSame('generated', $copy->getNodeId());
        self::assertSame($node->jsonSerialize(), $copy->jsonSerialize());
    }

    public function testRejectsEmptyNodeId(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Service node ID cannot be empty.');
        new ServiceNode('User', nodeId: '');
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $this->expectException(RpcException::class);
        new ServiceNode('User', nodeId: 'node', port: 0);
    }
}
