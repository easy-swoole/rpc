<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Exception\RpcException;

use EasySwoole\Rpc\Server\ServiceNode;
use PHPUnit\Framework\TestCase;

final class ServiceNodeTest extends TestCase
{
    public function testNodeIdentitySurvivesSerialization(): void
    {
        $node = new ServiceNode('User', nodeId: 'generated', host: '127.0.0.1', port: 9601, nodeWeight: 20);
        $copy = ServiceNode::fromArray($node->jsonSerialize());
        self::assertNotSame($node, $copy);
        self::assertSame('generated', $copy->getNodeId());
        self::assertSame($node->jsonSerialize(), $copy->jsonSerialize());
        self::assertArrayNotHasKey('listenAddress', $copy->jsonSerialize());
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

    public function testRejectsEmptyHost(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('host cannot be empty');

        new ServiceNode('User', nodeId: 'node', host: '   ');
    }

    public function testUsesLoopbackHostByDefault(): void
    {
        self::assertSame('127.0.0.1', (new ServiceNode('User', nodeId: 'node'))->getHost());
    }

    public function testRejectsWeightBelowOne(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('at least 1');

        new ServiceNode('User', nodeId: 'node', nodeWeight: 0);
    }

    public function testRejectsInvalidSerializedFieldTypes(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('invalid or missing fields');
        ServiceNode::fromArray([
            'serviceName' => ['User'],
            'nodeId' => 'node',
            'nodeName' => 'node-one',
            'nodeWeight' => 10,
            'host' => '127.0.0.1',
            'port' => 9601,
        ]);
    }
}
