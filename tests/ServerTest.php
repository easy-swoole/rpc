<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Rpc\AbstractInterface\AbstractService;
use EasySwoole\Rpc\AbstractInterface\AbstractServiceModule;
use EasySwoole\Rpc\Attribute\RpcAction;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\Protocol;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\Server\AssistWorker;
use EasySwoole\Rpc\Server\ServerAttachStatus;
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

    public function testDispatchesModuleAction(): void
    {
        $response = $this->server->handle(new Request('Test.Echo.value', ['a' => 1], requestUUID: 'id'));
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

    public function testRegistrationFreezesServiceModules(): void
    {
        $service = new TestService();
        $server = new Server(new SwooleTableNodeManager(16));
        $server->registerService($service);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is frozen');
        $service->addModule(new EchoModule());
    }

    public function testRejectsDuplicateModuleRegistration(): void
    {
        $service = new TestService();
        $service->addModule(new EchoModule());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already registered');
        $service->addModule(new EchoModule());
    }

    public function testCreatesAssistWorkerProcess(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $worker = new AssistWorker([], $manager, 5000, new DiscoveryConfig());

        self::assertInstanceOf(AbstractProcess::class, $worker);
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
            $conflictingRpcServer->attachServer($swooleServer);
            self::fail('An existing Swoole listener must not be reused implicitly for RPC.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('already registered', $exception->getMessage());
        }
        self::assertSame(ServerAttachStatus::FAILED, $conflictingRpcServer->getAttachStatus());
        self::assertCount(1, $swooleServer->ports);

        $errno = 0;
        $error = '';
        $occupied = stream_socket_server('udp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND);
        self::assertIsResource($occupied, $error);
        $address = stream_socket_get_name($occupied, false);
        self::assertIsString($address);
        $udpPort = (int) substr($address, strrpos($address, ':') + 1);
        $failedRpcServer = new Server(
            new SwooleTableNodeManager(16),
            new ServerConfig(listenAddress: '127.0.0.1', listenPort: $rpcPort),
            new DiscoveryConfig(listen: true, listenAddress: '127.0.0.1', listenPort: $udpPort, broadcast: false),
        );
        try {
            $failedRpcServer->attachServer($swooleServer);
            self::fail('Unavailable UDP discovery must fail before the RPC listener is created.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Unable to listen on RPC discovery UDP address', $exception->getMessage());
        } finally {
            fclose($occupied);
        }
        self::assertSame(ServerAttachStatus::FAILED, $failedRpcServer->getAttachStatus());
        self::assertCount(1, $swooleServer->ports);

        $rpcServer = new Server(
            new SwooleTableNodeManager(16),
            new ServerConfig(listenAddress: '127.0.0.1', listenPort: $rpcPort),
            new DiscoveryConfig(listen: false, broadcast: false),
        );

        $rpcServer->registerService(new TestService());
        self::assertFalse($rpcServer->hasAttach());
        self::assertSame(ServerAttachStatus::PENDING, $rpcServer->getAttachStatus());
        $rpcServer->attachServer($swooleServer);

        self::assertTrue($rpcServer->hasAttach());
        self::assertSame(ServerAttachStatus::ATTACHED, $rpcServer->getAttachStatus());
        self::assertCount(2, $swooleServer->ports);
        self::assertSame($rpcPort, $swooleServer->ports[1]->port);
        self::assertInstanceOf(
            AssistWorker::class,
            (new \ReflectionProperty($rpcServer, 'assistWorker'))->getValue($rpcServer),
        );

        try {
            $rpcServer->registerService(new WeightedService());
            self::fail('Services must not be registered after the RPC Server is attached.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('cannot be registered after', $exception->getMessage());
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already attached');
        $rpcServer->attachServer($swooleServer);
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

    public function testAssistWorkerMarksLocalServicesOfflineDuringShutdown(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $server = new Server($manager);
        $server->registerService(new TestService());
        $server->onlineService('Test');
        self::assertNotEmpty($manager->getNodes('Test'));

        $worker = new AssistWorker(
            $server->allServiceNodes(),
            $manager,
            5000,
            new DiscoveryConfig(listen: false, broadcast: false),
        );
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
        self::assertTrue($server->offlineService('Test'));
        self::assertSame([], $manager->getNodes('Test'));
    }

    public function testLifecycleRejectsUnknownService(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->server->onlineService('Missing');
    }

    public function testReturnsPreciseNotFoundStatuses(): void
    {
        self::assertSame(ResponseStatus::SERVICE_NOT_EXIST, $this->server->handle(new Request('Missing.Echo.action'))->getStatus());
        self::assertSame(ResponseStatus::MODULE_NOT_EXIST, $this->server->handle(new Request('Test.Missing.action'))->getStatus());
        self::assertSame(ResponseStatus::ACTION_NOT_EXIST, $this->server->handle(new Request('Test.Echo.missing'))->getStatus());
    }

    public function testServiceOnlyRoutesModulesAndModuleOnlyExposesAttributedActions(): void
    {
        self::assertSame(ResponseStatus::ACTION_NOT_EXIST, $this->server->handle(new Request('Test.Echo.internalHelper'))->getStatus());
    }

    public function testConvertsModuleExceptionsToResponses(): void
    {
        $warning = null;
        set_error_handler(function (int $severity, string $message) use (&$warning): bool {
            if ($severity === E_USER_WARNING) {
                $warning = $message;
                return true;
            }
            return false;
        });
        try {
            $module = $this->server->handle(new Request('Test.Echo.fail'));
        } finally {
            restore_error_handler();
        }

        self::assertSame([ResponseStatus::SERVICE_ERROR, 'Server error.'], [$module->getStatus(), $module->getMsg()]);
        self::assertStringContainsString('module failed', (string) $warning);
    }

    public function testHandledBusinessExceptionIsNotReportedToGlobalServerHandler(): void
    {
        $reported = false;
        $this->server->setOnException(function () use (&$reported): void { $reported = true; });

        set_error_handler(static fn (int $severity): bool => $severity === E_USER_WARNING);
        try {
            $response = $this->server->handle(new Request('Test.Echo.fail'));
        } finally {
            restore_error_handler();
        }

        self::assertFalse($reported);
        self::assertSame('Server error.', $response->getMsg());
    }

    public function testThrowingPhpErrorHandlerCannotBreakBusinessErrorResponse(): void
    {
        $reported = false;
        $this->server->setOnException(function () use (&$reported): void { $reported = true; });
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            $response = $this->server->handle(new Request('Test.Echo.fail'));
        } finally {
            restore_error_handler();
        }

        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
        self::assertSame('Server error.', $response->getMsg());
        self::assertFalse($reported);
    }

    public function testDefaultServiceExceptionHidesInternalMessage(): void
    {
        $service = new class extends AbstractService {
            public function serviceName(): string { return 'Broken'; }
            protected function onRequest(Request $request): bool
            {
                throw new \RuntimeException('sensitive service detail');
            }
        };
        $this->server->registerService($service);
        $warning = null;
        set_error_handler(function (int $severity, string $message) use (&$warning): bool {
            if ($severity === E_USER_WARNING) {
                $warning = $message;
                return true;
            }
            return false;
        });
        try {
            $response = $this->server->handle(new Request('Broken.Any.action'));
        } finally {
            restore_error_handler();
        }

        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
        self::assertSame('Server error.', $response->getMsg());
        self::assertStringContainsString('sensitive service detail', (string) $warning);
    }

    public function testReportsUnhandledExceptionToGlobalServerHandler(): void
    {
        $this->registerUnexpectedModule();
        $reported = null;
        $this->server->setOnException(function (\Throwable $throwable) use (&$reported): void {
            $reported = $throwable->getMessage();
        });

        $response = $this->server->handle(new Request('Unexpected.Module.execute'));

        self::assertSame('unexpected after-request failure', $reported);
        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
    }

    public function testGlobalExceptionHandlerCannotBreakResponseLifecycle(): void
    {
        $this->registerUnexpectedModule();
        $this->server->setOnException(static function (): never {
            throw new \RuntimeException('logger failed');
        });

        $response = $this->server->handle(new Request('Unexpected.Module.execute'));

        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
        self::assertSame('Server error.', $response->getMsg());
    }

    public function testIllegalClientPacketIsNotReportedToGlobalServerHandler(): void
    {
        $reported = false;
        $this->server->setOnException(function () use (&$reported): void { $reported = true; });

        $response = (new \ReflectionMethod($this->server, 'handlePacket'))
            ->invoke($this->server, 'invalid packet');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(ResponseStatus::ILLEGAL_PACKAGE, $response->getStatus());
        self::assertSame('Illegal RPC request.', $response->getMsg());
        self::assertFalse($reported);
    }

    public function testNetworkResponseUsesTheSharedApiKeySignature(): void
    {
        $security = new SecurityConfig(apiKey: 'shared-api-key');
        $server = new Server(
            new SwooleTableNodeManager(16),
            securityConfig: $security,
        );
        $server->registerService((new TestService())->addModule(new EchoModule()));
        $request = (new Request('Test.Echo.value', ['id' => 1], requestUUID: 'request-id'))
            ->sign($security);

        $response = (new \ReflectionMethod($server, 'handlePacket'))->invoke(
            $server,
            Protocol::pack(json_encode($request, JSON_THROW_ON_ERROR)),
        );

        self::assertInstanceOf(Response::class, $response);
        $response->verify($security);
        self::assertSame('request-id', $response->getResponseUUID());
    }

    public function testMissingModuleAndActionAreHandledByTheirOwningLayer(): void
    {
        $service = new class extends AbstractService {
            public function serviceName(): string { return 'Hook'; }
            protected function moduleNotFound(Request $request, Response $response): Response
            {
                return $response->setStatus(ResponseStatus::MODULE_NOT_EXIST)->setMsg('custom module missing');
            }
        };
        $service->addModule(new class extends AbstractServiceModule {
            public function moduleName(): string { return 'Custom'; }
            protected function actionNotFound(Request $request, Response $response): Response
            {
                return $response->setStatus(ResponseStatus::ACTION_NOT_EXIST)->setMsg('custom action missing');
            }
        });
        $this->server->registerService($service);

        $missingModule = $this->server->handle(new Request('Hook.Missing.action'));
        $missingAction = $this->server->handle(new Request('Hook.Custom.action'));

        self::assertSame('custom module missing', $missingModule->getMsg());
        self::assertSame('custom action missing', $missingAction->getMsg());
    }

    private function registerUnexpectedModule(): void
    {
        $service = new class extends AbstractService {
            public function serviceName(): string { return 'Unexpected'; }
            protected function afterRequest(Request $request, Response $response): void
            {
                throw new \RuntimeException('unexpected after-request failure');
            }
        };
        $service->addModule(new class extends AbstractServiceModule {
            public function moduleName(): string { return 'Module'; }
            #[RpcAction]
            public function execute(): string { return 'ok'; }
        });
        $this->server->registerService($service);
    }
}
