<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Server\ServerConfig;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use PHPUnit\Framework\TestCase;

final class RpcTest extends TestCase
{
    public function testUsesSharedSwooleTableManagerByDefault(): void
    {
        $rpc = new Rpc();
        self::assertInstanceOf(SwooleTableNodeManager::class, $rpc->getNodeManager());
        self::assertSame($rpc->getNodeManager(), $rpc->getNodeManager());
        self::assertSame($rpc->server(), $rpc->server());
    }

    public function testRpcInstancesAreIndependent(): void
    {
        $first = new Rpc();
        $second = new Rpc();
        self::assertNotSame($first, $second);
        self::assertNotSame($first->getNodeManager(), $second->getNodeManager());
        self::assertNotSame($first->server(), $second->server());
    }

    public function testPassesServerConfigToServer(): void
    {
        $config = new ServerConfig(advertiseIp: '10.0.0.2', listenPort: 9700);
        $rpc = new Rpc(serverConfig: $config);
        self::assertSame($config, $rpc->server()->getConfig());
    }
}
