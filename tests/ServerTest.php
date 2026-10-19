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
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Protocol\ProtocolCodec;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\Server\AssistWorker;
use EasySwoole\Rpc\Server\AssistWorkerConfig;
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
        self::assertSame('id', $response->getRequestUUID());
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

    public function testUsesConfiguredStableNodeId(): void
    {
        $server = new Server(
            new SwooleTableNodeManager(16),
            new ServerConfig(nodeId: 'stable-node-id'),
        );
        $server->registerService(new TestService());

        self::assertSame('stable-node-id', $server->getServiceNode('Test')?->getNodeId());
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
        $this->expectException(RpcException::class);
        $this->server->registerService(new TestService());
    }

    public function testRegistrationFreezesServiceModules(): void
    {
        $service = new TestService();
        $server = new Server(new SwooleTableNodeManager(16));
        $server->registerService($service);

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('is frozen');
        $service->addModule(new EchoModule());
    }

    public function testRejectsDuplicateModuleRegistration(): void
    {
        $service = new TestService();
        $service->addModule(new EchoModule());

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('already registered');
        $service->addModule(new EchoModule());
    }

    public function testCreatesAssistWorkerProcess(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $worker = new AssistWorker([], $manager);

        self::assertInstanceOf(AbstractProcess::class, $worker);
        self::assertSame('rpc-assist-worker', $worker->getProcessName());
    }

    public function testAssistWorkerProcessNameContainsNodeName(): void
    {
        $worker = new AssistWorker(
            [],
            new SwooleTableNodeManager(16),
            new AssistWorkerConfig(nodeName: 'user-rpc-1'),
        );

        self::assertSame('rpc-assist-worker.user-rpc-1', $worker->getProcessName());
    }

    public function testAssistWorkerConfigCollectsServerConstraints(): void
    {
        $server = new ServerConfig(nodeName: 'user-rpc-1', heartbeatIntervalMs: 3000);
        $discovery = new DiscoveryConfig(listen: false, broadcast: false);
        $security = new SecurityConfig(apiKey: 'secret');

        $config = AssistWorkerConfig::fromServer($server, $discovery, $security);

        self::assertSame('user-rpc-1', $config->nodeName);
        self::assertSame(3000, $config->heartbeatIntervalMs);
        self::assertSame($discovery, $config->discovery);
        self::assertSame($security, $config->security);
    }

    public function testAssistWorkerConfigRejectsInvalidHeartbeatInterval(): void
    {
        $this->expectException(RpcException::class);
        new AssistWorkerConfig(heartbeatIntervalMs: 0);
    }

    public function testServerConfigRejectsExplicitEmptyNodeId(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('node ID cannot be empty');
        new ServerConfig(nodeId: '');
    }

    public function testRejectsHeartbeatIntervalThatCanOutliveNodeTtl(): void
    {
        $server = new Server(
            new SwooleTableNodeManager(16, nodeTtlSeconds: 5),
            new ServerConfig(heartbeatIntervalMs: 5000),
        );
        $attacher = (new \ReflectionProperty($server, 'attacher'))->getValue($server);

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('greater than the heartbeat interval');
        (new \ReflectionMethod($attacher, 'assertNodeTtlIntervals'))->invoke($attacher);
    }

    public function testRejectsBroadcastIntervalThatCanOutliveNodeTtl(): void
    {
        $server = new Server(
            new SwooleTableNodeManager(16, nodeTtlSeconds: 5),
            new ServerConfig(heartbeatIntervalMs: 1000),
            new DiscoveryConfig(broadcast: true, broadcastIntervalMs: 5000),
        );
        $attacher = (new \ReflectionProperty($server, 'attacher'))->getValue($server);

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('greater than the discovery broadcast interval');
        (new \ReflectionMethod($attacher, 'assertNodeTtlIntervals'))->invoke($attacher);
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
        } catch (RpcException $exception) {
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
        } catch (RpcException $exception) {
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
        try {
            $rpcServer->registerService(new WeightedService());
            self::fail('Services must not be registered after the RPC Server is attached.');
        } catch (RpcException $exception) {
            self::assertStringContainsString('cannot be registered after', $exception->getMessage());
        }

        $this->expectException(RpcException::class);
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
            $this->expectException(RpcException::class);
            $this->expectExceptionMessage('Unable to listen on RPC discovery UDP address');
            $attacher = (new \ReflectionProperty($rpcServer, 'attacher'))->getValue($rpcServer);
            (new \ReflectionMethod($attacher, 'assertUdpAvailable'))->invoke($attacher);
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
            new AssistWorkerConfig(
                discovery: new DiscoveryConfig(listen: false, broadcast: false),
            ),
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
        $this->expectException(RpcException::class);
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

    public function testModuleActionMetadataIsPreparedWithoutRequiringParentConstructor(): void
    {
        $service = new class extends AbstractService {
            public function serviceName(): string { return 'Constructed'; }
        };
        $module = new class('dependency') extends AbstractServiceModule {
            public function __construct(private readonly string $dependency) {}
            public function moduleName(): string { return 'Custom'; }
            #[RpcAction]
            public function execute(): string { return $this->dependency; }
        };
        $service->addModule($module);
        $this->server->registerService($service);

        $response = $this->server->handle(new Request('Constructed.Custom.execute'));

        self::assertSame(ResponseStatus::OK, $response->getStatus());
        self::assertSame('dependency', $response->getResult());
    }

    public function testConvertsModuleExceptionsToResponses(): void
    {
        $reported = null;
        $this->server->setOnException(function (\Throwable $throwable) use (&$reported): void {
            $reported = $throwable;
        });

        $module = $this->server->handle(new Request('Test.Echo.fail'));

        self::assertSame([ResponseStatus::SERVICE_ERROR, 'Server error.'], [$module->getStatus(), $module->getMsg()]);
        self::assertSame(NodeHealthStatus::SUSPECT, $module->getNodeHealthStatus());
        self::assertInstanceOf(\RuntimeException::class, $reported);
        self::assertSame('module failed', $reported->getMessage());
    }

    public function testHandledBusinessExceptionIsNotReportedToGlobalServerHandler(): void
    {
        $service = new class extends AbstractService {
            public function serviceName(): string { return 'Handled'; }
        };
        $service->addModule(new class extends AbstractServiceModule {
            public function moduleName(): string { return 'Business'; }
            #[RpcAction]
            public function execute(): never { throw new \RuntimeException('handled business failure'); }
            protected function onException(\Throwable $throwable, Response $response): void
            {
                $response
                    ->setStatus(ResponseStatus::SERVICE_ERROR)
                    ->setNodeHealthStatus(NodeHealthStatus::HEALTHY)
                    ->setMsg('Business operation failed.');
            }
        });
        $this->server->registerService($service);
        $reported = false;
        $this->server->setOnException(function () use (&$reported): void { $reported = true; });

        $response = $this->server->handle(new Request('Handled.Business.execute'));

        self::assertFalse($reported);
        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
        self::assertSame(NodeHealthStatus::HEALTHY, $response->getNodeHealthStatus());
        self::assertSame('Business operation failed.', $response->getMsg());
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
        $reported = null;
        $this->server->setOnException(function (\Throwable $throwable) use (&$reported): void {
            $reported = $throwable;
        });

        $response = $this->server->handle(new Request('Broken.Any.action'));

        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
        self::assertSame('Server error.', $response->getMsg());
        self::assertInstanceOf(\RuntimeException::class, $reported);
        self::assertSame('sensitive service detail', $reported->getMessage());
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
        self::assertNull($response->getResult());
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

        $codec = new ProtocolCodec(new PayloadSigner(new SecurityConfig()));
        $handler = (new \ReflectionProperty($this->server, 'handler'))->getValue($this->server);
        $response = $codec->decodeResponse($handler->handlePacket('invalid packet'));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(ResponseStatus::ILLEGAL_PACKAGE, $response->getStatus());
        self::assertSame('Illegal RPC request.', $response->getMsg());
        self::assertFalse($reported);
    }

    public function testInvalidTcpFrameReturnsIllegalPackageWithoutRuntimeError(): void
    {
        $reported = false;
        $this->server->setOnRuntimeError(function (string $message) use (&$reported): void {
            $reported = true;
        });
        $attacher = (new \ReflectionProperty($this->server, 'attacher'))->getValue($this->server);
        $packet = (new \ReflectionMethod($attacher, 'buildResponsePacket'))->invoke($attacher, 'invalid frame');
        $codec = new ProtocolCodec(new PayloadSigner(new SecurityConfig()));
        $response = $codec->decodeResponse(Protocol::unpack($packet));

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
        $codec = new ProtocolCodec(new PayloadSigner($security));
        $request = new Request('Test.Echo.value', ['id' => 1], requestUUID: 'request-id');
        $handler = (new \ReflectionProperty($server, 'handler'))->getValue($server);
        $response = $codec->decodeResponse($handler->handlePacket($codec->encodeRequest($request)));

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('request-id', $response->getRequestUUID());
    }

    public function testUnserializableActionResultReturnsServiceErrorWithRequestUuid(): void
    {
        $service = new class extends AbstractService {
            public function serviceName(): string { return 'Serialization'; }
        };
        $service->addModule(new class extends AbstractServiceModule {
            public function moduleName(): string { return 'Result'; }
            #[RpcAction]
            public function invalid(): mixed { return fopen('php://memory', 'r'); }
        });
        $this->server->registerService($service);
        $reported = null;
        $this->server->setOnException(function (\Throwable $throwable) use (&$reported): void {
            $reported = $throwable;
        });
        $codec = new ProtocolCodec(new PayloadSigner(new SecurityConfig()));
        $handler = (new \ReflectionProperty($this->server, 'handler'))->getValue($this->server);

        $response = $codec->decodeResponse($handler->handlePacket(
            $codec->encodeRequest(new Request('Serialization.Result.invalid', requestUUID: 'request-id')),
        ));

        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
        self::assertSame(NodeHealthStatus::SUSPECT, $response->getNodeHealthStatus());
        self::assertSame('request-id', $response->getRequestUUID());
        self::assertSame('Server error.', $response->getMsg());
        self::assertInstanceOf(RpcException::class, $reported);
    }

    public function testActionCannotReturnAResponseObject(): void
    {
        $service = new class extends AbstractService {
            public function serviceName(): string { return 'CustomResponse'; }
        };
        $service->addModule(new class extends AbstractServiceModule {
            public function moduleName(): string { return 'Result'; }
            #[RpcAction]
            public function invalid(): Response { return new Response('another-request'); }
        });
        $this->server->registerService($service);

        set_error_handler(static fn (int $severity): bool => $severity === E_USER_WARNING);
        try {
            $response = $this->server->handle(
                new Request('CustomResponse.Result.invalid', requestUUID: 'request-id'),
            );
        } finally {
            restore_error_handler();
        }

        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
        self::assertSame('request-id', $response->getRequestUUID());
    }

    public function testActionCanModifyInjectedResponseAndReturnBusinessData(): void
    {
        $service = new class extends AbstractService {
            public function serviceName(): string { return 'InjectedResponse'; }
        };
        $service->addModule(new class extends AbstractServiceModule {
            public function moduleName(): string { return 'Result'; }
            #[RpcAction]
            public function execute(mixed $arg, Request $request, Response $response): array
            {
                $response->setMsg('custom message');
                return ['requestUUID' => $request->getRequestUUID()];
            }
        });
        $this->server->registerService($service);

        $response = $this->server->handle(
            new Request('InjectedResponse.Result.execute', requestUUID: 'request-id'),
        );

        self::assertSame('custom message', $response->getMsg());
        self::assertSame(['requestUUID' => 'request-id'], $response->getResult());
        self::assertSame('request-id', $response->getRequestUUID());
    }

    public function testMissingModuleAndActionAreHandledByTheirOwningLayer(): void
    {
        $service = new class extends AbstractService {
            public function serviceName(): string { return 'Hook'; }
            protected function moduleNotFound(Request $request, Response $response): void
            {
                $response->setStatus(ResponseStatus::MODULE_NOT_EXIST)->setMsg('custom module missing');
            }
        };
        $service->addModule(new class extends AbstractServiceModule {
            public function moduleName(): string { return 'Custom'; }
            protected function actionNotFound(Request $request, Response $response): void
            {
                $response->setStatus(ResponseStatus::ACTION_NOT_EXIST)->setMsg('custom action missing');
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
