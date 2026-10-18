<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\Server\ServerConfig;
use EasySwoole\Rpc\Server\DiscoveryConfig;
use EasySwoole\Rpc\Tests\Fixtures\EchoModule;
use EasySwoole\Rpc\Tests\Fixtures\TestService;
use EasySwoole\Rpc\Tests\Fixtures\WeightedService;
use EasySwoole\Rpc\Utility\MemoryNodeManager;
use PHPUnit\Framework\TestCase;

final class ServerTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server(
            new MemoryNodeManager(),
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
        $server = new Server(new MemoryNodeManager(), new ServerConfig(nodeName: 'default'));
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
        self::assertInstanceOf(\Swoole\Process::class, $worker->process());
        self::assertSame($worker->process(), $worker->process());
    }

    public function testServerCoordinatesServiceNodeLifecycle(): void
    {
        $manager = new MemoryNodeManager();
        $server = new Server($manager);
        $server->registerService(new TestService());

        self::assertTrue($server->onlineService('Test'));
        self::assertNotNull($manager->getNode('Test'));
        self::assertTrue($server->heartbeatService('Test'));
        self::assertTrue($server->hibernateService('Test'));
        self::assertNull($manager->getNode('Test'));
        self::assertTrue($server->onlineService('Test'));
        self::assertTrue($server->offlineService('Test'));
        self::assertNull($manager->getNode('Test'));
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

    public function testConvertsServiceAndModuleExceptionsToResponses(): void
    {
        $service = $this->server->handle(new Request('Test.fail'));
        $module = $this->server->handle(new Request('Test.Echo.fail'));
        self::assertSame([Response::STATUS_SERVICE_ERROR, 'service failed'], [$service->getStatus(), $service->getMsg()]);
        self::assertSame([Response::STATUS_SERVICE_ERROR, 'module failed'], [$module->getStatus(), $module->getMsg()]);
    }
}
