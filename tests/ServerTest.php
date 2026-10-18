<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\Server\ServerConfig;
use EasySwoole\Rpc\Server\DiscoveryConfig;
use EasySwoole\Rpc\Tests\Fixtures\EchoModule;
use EasySwoole\Rpc\Tests\Fixtures\TestService;
use EasySwoole\Rpc\Tests\Fixtures\WeightedService;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use PHPUnit\Framework\TestCase;

final class ServerTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server(
            new SwooleTableNodeManager(16),
            new ServerConfig(advertiseIp: '10.0.0.8', listenPort: 9600, nodeName: 'rpc-server'),
        );
        $this->server->registerService((new TestService())->addModule(new EchoModule()));
    }

    public function testDispatchesServiceAction(): void
    {
        $response = $this->server->handle(new Request('Test.echo', ['a' => 1], requestUUID: 'id'));
        self::assertTrue($response->isSuccess());
        self::assertSame(['a' => 1], $response->getResult());
        self::assertSame('id', $response->getResponseUUID());
    }

    public function testRegisteredServiceOwnsCompleteServiceNode(): void
    {
        $node = $this->server->getServiceNode('Test');
        self::assertNotNull($node);
        self::assertSame('Test', $node->getServiceName());
        self::assertNotSame('', $node->getNodeId());
        self::assertSame('10.0.0.8', $node->getIp());
        self::assertSame(9600, $node->getPort());
        self::assertSame('rpc-server', $node->getNodeName());
        self::assertSame($node, $this->server->allServiceNodes()['Test']);
    }

    public function testWeightIsDeclaredByService(): void
    {
        $server = new Server(new SwooleTableNodeManager(16), new ServerConfig(nodeName: 'default'));
        $server->registerService(new WeightedService());
        self::assertSame('default', $server->getServiceNode('Weighted')?->getNodeName());
        self::assertSame(25, $server->getServiceNode('Weighted')?->getNodeWeight());
    }

    public function testRegistrationRejectsDuplicateService(): void
    {
        $this->expectException(\LogicException::class);
        $this->server->registerService(new TestService());
    }

    public function testCreatesIndependentAssistProcess(): void
    {
        $worker = $this->server->createAssistWorker(new DiscoveryConfig(listen: false, broadcast: false));
        self::assertInstanceOf(AbstractProcess::class, $worker);
        self::assertInstanceOf(\Swoole\Process::class, $worker->getProcess());
        self::assertSame($worker->getProcess(), $worker->getProcess());
        self::assertSame('rpc-assist-worker', $worker->getProcessName());
    }

    public function testRejectsRegisteredPortAndCreatesMissingRpcSubPort(): void
    {
        $mainPort = random_int(20000, 30000);
        $rpcPort = $mainPort + 1;
        $swooleServer = new \Swoole\Server('127.0.0.1', $mainPort, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $conflictingRpcServer = new Server(
            new SwooleTableNodeManager(16),
            new ServerConfig(listenAddress: '127.0.0.1', listenPort: $mainPort),
            new DiscoveryConfig(listen: false, broadcast: false),
        );

        try {
            (new \ReflectionMethod($conflictingRpcServer, 'resolveListener'))->invoke($conflictingRpcServer, $swooleServer);
            self::fail('An existing Swoole listener must not be reused implicitly for RPC.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('already registered', $exception->getMessage());
        }
        self::assertCount(1, $swooleServer->ports);

        $rpcServer = new Server(
            new SwooleTableNodeManager(16),
            new ServerConfig(listenAddress: '127.0.0.1', listenPort: $rpcPort),
            new DiscoveryConfig(listen: false, broadcast: false),
        );

        $rpcServer->registerService(new TestService());
        $rpcServer->attachServer($swooleServer);

        self::assertCount(2, $swooleServer->ports);
        self::assertSame($rpcPort, $swooleServer->ports[1]->port);
        self::assertInstanceOf(
            \EasySwoole\Rpc\Server\AssistWorker::class,
            (new \ReflectionProperty($rpcServer, 'assistWorker'))->getValue($rpcServer),
        );
    }

    public function testRejectsUnavailableUdpDiscoveryAddressBeforeWorkerStarts(): void
    {
        $errno = 0;
        $error = '';
        $occupied = stream_socket_server('udp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND);
        self::assertIsResource($occupied, $error);
        $address = stream_socket_get_name($occupied, false);
        self::assertIsString($address);
        $udpPort = (int) substr($address, strrpos($address, ':') + 1);
        $rpcServer = new Server(
            new SwooleTableNodeManager(16),
            new ServerConfig(),
            new DiscoveryConfig(listen: true, listenAddress: '127.0.0.1', listenPort: $udpPort, broadcast: false),
        );

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unable to listen on RPC discovery UDP address');
            (new \ReflectionMethod($rpcServer, 'assertUdpListenAvailable'))->invoke($rpcServer);
        } finally {
            fclose($occupied);
        }
    }

    public function testAssistProcessMarksLocalServicesOfflineDuringShutdown(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $server = new Server($manager);
        $server->registerService(new TestService());
        $server->onlineService('Test');
        self::assertNotEmpty($manager->getNodes('Test'));

        $worker = $server->createAssistWorker(new DiscoveryConfig(listen: false, broadcast: false));
        (new \ReflectionMethod($worker, 'onShutDown'))->invoke($worker);

        self::assertSame([], $manager->getNodes('Test'));
    }

    public function testServerCoordinatesServiceNodeLifecycle(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $server = new Server($manager);
        $server->registerService(new TestService());

        self::assertTrue($server->onlineService('Test'));
        self::assertNotEmpty($manager->getNodes('Test'));
        self::assertTrue($server->heartbeatService('Test'));
        self::assertTrue($server->hibernateService('Test'));
        self::assertSame([], $manager->getNodes('Test'));
        self::assertTrue($server->onlineService('Test'));
        self::assertTrue($server->offlineService('Test'));
        self::assertSame([], $manager->getNodes('Test'));
    }

    public function testLifecycleRejectsUnknownService(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->server->onlineService('Missing');
    }

    public function testDispatchesLegacyModuleAction(): void
    {
        $response = $this->server->handle(new Request('Test.Echo.value', 'hello'));
        self::assertSame('hello', $response->getResult());
    }

    public function testReturnsPreciseNotFoundStatuses(): void
    {
        self::assertSame(Response::STATUS_SERVICE_NOT_EXIST, $this->server->handle(new Request('Missing.action'))->getStatus());
        self::assertSame(Response::STATUS_MODULE_NOT_EXIST, $this->server->handle(new Request('Test.Missing.action'))->getStatus());
        self::assertSame(Response::STATUS_ACTION_NOT_EXIST, $this->server->handle(new Request('Test.missing'))->getStatus());
    }

    public function testOnlyAttributedMethodsAreExposedAsActions(): void
    {
        self::assertSame(Response::STATUS_ACTION_NOT_EXIST, $this->server->handle(new Request('Test.serviceName'))->getStatus());
        self::assertSame(Response::STATUS_ACTION_NOT_EXIST, $this->server->handle(new Request('Test.serviceWeight'))->getStatus());
        self::assertSame(Response::STATUS_ACTION_NOT_EXIST, $this->server->handle(new Request('Test.internalHelper'))->getStatus());
        self::assertSame(Response::STATUS_ACTION_NOT_EXIST, $this->server->handle(new Request('Test.Echo.internalHelper'))->getStatus());
    }

    public function testConvertsServiceAndModuleExceptionsToResponses(): void
    {
        $service = $this->server->handle(new Request('Test.fail'));
        $module = $this->server->handle(new Request('Test.Echo.fail'));
        self::assertSame([Response::STATUS_SERVICE_ERROR, 'service failed'], [$service->getStatus(), $service->getMsg()]);
        self::assertSame([Response::STATUS_SERVICE_ERROR, 'module failed'], [$module->getStatus(), $module->getMsg()]);
    }
}
