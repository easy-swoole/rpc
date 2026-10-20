<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\RpcConfig;
use EasySwoole\Rpc\RpcDependencies;
use EasySwoole\Rpc\Server\DiscoveryConfig;
use EasySwoole\Rpc\Server\ServerConfig;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\ProtocolConfig;
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
        self::assertInstanceOf(ProtocolConfig::class, $config->getProtocol());
        self::assertSame($config->getServer(), $config->getServer());
        self::assertSame($config->getDiscovery(), $config->getDiscovery());
    }

    public function testDiscoveryConfigurationRejectsImplicitlyInvalidBroadcastSetup(): void
    {
        $this->expectException(RpcException::class);
        new DiscoveryConfig(broadcast: true, broadcastAddresses: []);
    }

    public function testDiscoveryConfigurationNormalizesBroadcastTargetsOnce(): void
    {
        $config = new DiscoveryConfig(
            broadcast: true,
            broadcastAddresses: ['255.255.255.255:9600', 'rpc-discovery.local:9700'],
        );

        self::assertSame([
            ['255.255.255.255', 9600],
            ['rpc-discovery.local', 9700],
        ], $config->broadcastTargets());
    }

    public function testProtocolConfigurationRejectsInvalidPackageSize(): void
    {
        $this->expectException(RpcException::class);
        new ProtocolConfig(maxPackageSize: 3);
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

    public function testUdpDiscoveryIsDisabledByDefault(): void
    {
        $config = new DiscoveryConfig();

        self::assertFalse($config->listen);
        self::assertFalse($config->broadcast);
    }

    public function testDiscoveryConfigRejectsInvalidSocketRetryIntervalWhenEnabled(): void
    {
        $this->expectException(RpcException::class);
        new DiscoveryConfig(listen: true, socketRetryIntervalMs: 0);
    }

    public function testConfiguresServerRuntimeErrorHandler(): void
    {
        $rpc = new Rpc();
        $serverHandler = static function (string $message): void {};

        self::assertSame($rpc->server(), $rpc->server()->setOnRuntimeError($serverHandler));
        $attacher = (new \ReflectionProperty($rpc->server(), 'attacher'))->getValue($rpc->server());
        self::assertSame($serverHandler, (new \ReflectionProperty($attacher, 'onRuntimeError'))->getValue($attacher));
        self::assertSame($rpc->client(), $rpc->client());
    }
}
