<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Server\ServiceNode;
use PHPUnit\Framework\TestCase;

final class ServiceNodeTest extends TestCase
{
    public function testWithNodeIdReturnsNewImmutableNode(): void
    {
        $node = new ServiceNode('User', ip: '127.0.0.1', port: 9601, nodeWeight: 20);
        $identified = $node->withNodeId('generated');
        self::assertNotSame($node, $identified);
        self::assertSame('', $node->getNodeId());
        self::assertSame('generated', $identified->getNodeId());
        self::assertSame($node->getServiceName(), $identified->getServiceName());
        self::assertSame($node->getIp(), $identified->getIp());
        self::assertSame($node->getPort(), $identified->getPort());
        self::assertSame($node->getNodeWeight(), $identified->getNodeWeight());
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ServiceNode('User', port: 0);
    }
}
