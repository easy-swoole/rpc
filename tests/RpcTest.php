<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\RpcConfig;
use EasySwoole\Rpc\RpcDependencies;
use EasySwoole\Rpc\Server\DiscoveryConfig;
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
        $rpc = new Rpc(config: new RpcConfig(server: $config));
        self::assertSame($config, $rpc->server()->getConfig());
    }

    public function testConfigCreatesImmutableDefaultsAndExposesGetters(): void
    {
        $config = new RpcConfig();
        self::assertInstanceOf(ServerConfig::class, $config->getServer());
        self::assertInstanceOf(DiscoveryConfig::class, $config->getDiscovery());
        self::assertSame($config->getServer(), $config->getServer());
        self::assertSame($config->getDiscovery(), $config->getDiscovery());
    }

    public function testDependenciesCreateDefaultComponents(): void
    {
        $dependencies = new RpcDependencies();
        self::assertInstanceOf(SwooleTableNodeManager::class, $dependencies->getNodeManager());
        self::assertSame($dependencies->getNodeManager(), $dependencies->getNodeManager());
    }

    public function testRpcUsesInjectedDependencies(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $rpc = new Rpc(dependencies: new RpcDependencies(nodeManager: $manager));
        self::assertSame($manager, $rpc->getNodeManager());
    }

    public function testDiscoveryConfigIsImmutable(): void
    {
        $config = new DiscoveryConfig(listen: false);
        $this->expectException(\Error::class);
        $config->listen = true;
    }
}
