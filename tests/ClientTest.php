<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Client\Client;
use EasySwoole\Rpc\Client\RequestBatch;
use EasySwoole\Rpc\Client\TcpTransport;
use EasySwoole\Rpc\Client\RequestExecutor;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Config\ProtocolConfig;
use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;
use EasySwoole\Rpc\Utility\WeightedRandomNodeSelector;
use EasySwoole\Rpc\Utility\ConsecutiveFailurePolicy;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testExecutesRequestAndCallbackThroughTcpTransport(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node'); $manager->online($node);
        $transport = new class extends TcpTransport {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            { return new Response($request->getRequestUUID(), result: $request->getArg()); }
        };
        $callbackResponse = null;
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->setArg('value')->setOnSuccess(function (Response $response) use (&$callbackResponse): void {
            $callbackResponse = $response;
        });
        $responses = $this->execBatch($batch);
        self::assertSame($responses['Test.Echo.value'], $callbackResponse);
        self::assertSame('value', $responses['Test.Echo.value']->getResult());
    }

    public function testReportsMissingNodeWithoutCallingTransport(): void
    {
        $client = new Client(
            new SwooleTableNodeManager(16),
            new WeightedRandomNodeSelector(),
            new ConsecutiveFailurePolicy(),
        );
        $batch = $client->buildRequest();
        $batch->addCall('Missing.Module.action');
        $response = $this->execBatch($batch)['Missing.Module.action'];
        self::assertSame(ResponseStatus::NOT_AVAILABLE_NODE, $response->getStatus());
        self::assertSame('', $response->getRequestUUID());
    }

    public function testRejectsExplicitNodeFromAnotherService(): void
    {
        $batch = (new Client(
            new SwooleTableNodeManager(16),
            new WeightedRandomNodeSelector(),
            new ConsecutiveFailurePolicy(),
        ))->buildRequest();
        $context = $batch->addCall('User.Profile.detail');

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('belongs to service "Order"');
        $context->setServiceNode(new ServiceNode('Order', nodeId: 'order-node'));
    }

    public function testRejectsNonPositiveTimeoutBeforeExecutingCalls(): void
    {
        $batch = (new Client(
            new SwooleTableNodeManager(16),
            new WeightedRandomNodeSelector(),
            new ConsecutiveFailurePolicy(),
        ))->buildRequest();
        $batch->addCall('Test.Echo.value');

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('greater than zero');
        $this->execBatch($batch, 0);
    }

    public function testClientExceptionHandlerConsumesActionCallbackException(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $reported = null;
        $client = $this->client(
            $manager,
            new ConsecutiveFailurePolicy(),
            new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response($request->getRequestUUID());
                }
            },
        );
        $client->setOnException(function (\Throwable $throwable) use (&$reported): void {
            $reported = $throwable;
        });
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->setOnSuccess(static function (): never {
            throw new \RuntimeException('client callback failed');
        });

        self::assertCount(1, $this->execBatch($batch));
        self::assertInstanceOf(\RuntimeException::class, $reported);
        self::assertSame('client callback failed', $reported->getMessage());
    }

    public function testClientExceptionHandlerObservesEveryConcurrentActionCallbackException(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = $this->client(
            $manager,
            new ConsecutiveFailurePolicy(),
            new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response($request->getRequestUUID());
                }
            },
        );
        $reported = [];
        $client->setOnException(static function (\Throwable $throwable) use (&$reported): void {
            $reported[] = $throwable->getMessage();
        });
        $batch = $client->buildRequest();
        foreach (['Test.Echo.first' => 'first callback failed', 'Test.Echo.second' => 'second callback failed'] as $path => $message) {
            $batch->addCall($path)->setOnSuccess(static function () use ($message): never {
                throw new \RuntimeException($message);
            });
        }

        self::assertCount(2, $this->execBatch($batch));

        sort($reported);
        self::assertSame(['first callback failed', 'second callback failed'], $reported);
    }

    public function testRequestBatchRequiresCoroutineEnvironment(): void
    {
        $batch = (new Client(
            new SwooleTableNodeManager(16),
            new WeightedRandomNodeSelector(),
            new ConsecutiveFailurePolicy(),
        ))->buildRequest();

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('inside a Swoole coroutine');
        $batch->exec();
    }

    public function testNodeRegistryQueryFailureReturnsLookupFailedResponse(): void
    {
        $manager = new class extends AbstractNodeManager {
            public function heartbeat(ServiceNode $node): bool { return true; }
            public function offline(ServiceNode $node): bool { return true; }
            public function online(ServiceNode $node): bool { return true; }
            public function getNodes(string $serviceName): array|false { return false; }
        };
        $client = new Client(
            $manager,
            new WeightedRandomNodeSelector(),
            new ConsecutiveFailurePolicy(),
        );
        $batch = $client->buildRequest();
        $failedResponse = null;
        $batch->addCall('Test.Echo.value')->setOnFail(static function (Response $response) use (&$failedResponse): void {
            $failedResponse = $response;
        });
        $responses = $this->execBatch($batch);

        self::assertCount(1, $responses);
        self::assertSame(ResponseStatus::NODE_LOOKUP_FAILED, $responses['Test.Echo.value']->getStatus());
        self::assertSame($responses['Test.Echo.value'], $failedResponse);
        self::assertSame('', $responses['Test.Echo.value']->getRequestUUID());
    }

    public function testUnexpectedNodeManagerExceptionUsesClientExceptionHandler(): void
    {
        $manager = new class extends AbstractNodeManager {
            public function heartbeat(ServiceNode $node): bool { return true; }
            public function offline(ServiceNode $node): bool { return true; }
            public function online(ServiceNode $node): bool { return true; }
            public function getNodes(string $serviceName): array|false
            {
                throw new \RuntimeException('unexpected registry failure');
            }
        };
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy());
        $reported = null;
        $client->setOnException(static function (\Throwable $throwable) use (&$reported): void {
            $reported = $throwable;
        });
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');

        \Swoole\Coroutine\run(static function () use ($batch): void {
            self::assertSame([], $batch->exec());
        });

        self::assertInstanceOf(\RuntimeException::class, $reported);
        self::assertSame('unexpected registry failure', $reported->getMessage());
    }

    public function testRetryOfflinesFailedNodeAndSelectsAnotherNode(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $failed = new ServiceNode('Test', nodeId: 'failed');
        $healthy = new ServiceNode('Test', nodeId: 'healthy');
        $manager->online($failed); $manager->online($healthy);
        $transport = new class extends TcpTransport {
            public array $visited = [];
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                $this->visited[] = $node->getNodeId();
                if ($node->getNodeId() === 'failed') {
                    return new Response(
                        $request->getRequestUUID(),
                        status: ResponseStatus::TRANSPORT_ERROR,
                        nodeHealthStatus: NodeHealthStatus::SUSPECT,
                        msg: 'down',
                    );
                }
                return new Response($request->getRequestUUID(), result: 'ok');
            }
        };
        $client = $this->client($manager, new ConsecutiveFailurePolicy(1), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->setServiceNode($failed)->enableRetry(1);
        self::assertTrue($this->execBatch($batch)['Test.Echo.value']->isSuccess());
        self::assertSame(['failed', 'healthy'], $transport->visited);
        self::assertSame(['healthy'], array_map(static fn (ServiceNode $node): string => $node->getNodeId(), $manager->getNodes('Test')));
    }

    public function testRetryExcludesPreviousNodeWhenAnotherNodeIsAvailable(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $failed = new ServiceNode('Test', nodeId: 'failed');
        $healthy = new ServiceNode('Test', nodeId: 'healthy');
        $manager->online($failed);
        $manager->online($healthy);
        $transport = new class extends TcpTransport {
            public array $visited = [];
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                $this->visited[] = $node->getNodeId();
                if ($node->getNodeId() === 'failed') {
                    return new Response(
                        $request->getRequestUUID(),
                        status: ResponseStatus::TRANSPORT_ERROR,
                        nodeHealthStatus: NodeHealthStatus::SUSPECT,
                    );
                }
                return new Response($request->getRequestUUID(), result: 'ok');
            }
        };
        $client = $this->client($manager, new ConsecutiveFailurePolicy(99), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->setServiceNode($failed)->enableRetry(1);

        self::assertTrue($this->execBatch($batch)['Test.Echo.value']->isSuccess());
        self::assertSame(['failed', 'healthy'], $transport->visited);
        self::assertCount(2, $manager->getNodes('Test'));
    }

    public function testRetryCanReuseTheOnlyAvailableNode(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'only');
        $manager->online($node);
        $transport = new class extends TcpTransport {
            public array $visited = [];
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                $this->visited[] = $node->getNodeId();
                return new Response(
                    $request->getRequestUUID(),
                    status: ResponseStatus::TRANSPORT_ERROR,
                    nodeHealthStatus: NodeHealthStatus::SUSPECT,
                );
            }
        };
        $client = $this->client($manager, new ConsecutiveFailurePolicy(99), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->enableRetry(1);

        self::assertSame(ResponseStatus::TRANSPORT_ERROR, $this->execBatch($batch)['Test.Echo.value']->getStatus());
        self::assertSame(['only', 'only'], $transport->visited);
    }

    public function testRetryPreservesLastFailureWhenOfflineLeavesNoNode(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'only');
        $manager->online($node);
        $client = $this->client($manager, new ConsecutiveFailurePolicy(1), new class extends TcpTransport {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                return new Response(
                    $request->getRequestUUID(),
                    status: ResponseStatus::TRANSPORT_ERROR,
                    nodeHealthStatus: NodeHealthStatus::SUSPECT,
                );
            }
        });
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->enableRetry(1);

        $response = $this->execBatch($batch)['Test.Echo.value'];
        self::assertSame(ResponseStatus::TRANSPORT_ERROR, $response->getStatus());
        self::assertNotSame('', $response->getRequestUUID());
    }

    public function testExecutesQueuedRequestsConcurrently(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node'); $manager->online($node);
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), new class extends TcpTransport {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                \Swoole\Coroutine::sleep(0.05);
                return new Response($request->getRequestUUID());
            }
        });
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');
        $batch->addCall('Test.Echo.other');
        $start = microtime(true);
        self::assertSame(['Test.Echo.value', 'Test.Echo.other'], array_keys($this->execBatch($batch)));
        self::assertLessThan(0.09, microtime(true) - $start);
    }

    public function testSameClientBuildsIndependentConcurrentRequestBatches(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), new class extends TcpTransport {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                \Swoole\Coroutine::sleep(0.01);
                return new Response($request->getRequestUUID(), result: $request->getArg());
            }
        });
        $first = $client->buildRequest();
        $first->addCall('Test.Echo.value')->setArg('first');
        $second = $client->buildRequest();
        $second->addCall('Test.Echo.value')->setArg('second');
        $results = [];

        \Swoole\Coroutine\run(function () use ($first, $second, &$results): void {
            $waitGroup = new \Swoole\Coroutine\WaitGroup();
            foreach (['first' => $first, 'second' => $second] as $key => $batch) {
                $waitGroup->add();
                \Swoole\Coroutine::create(function () use ($key, $batch, &$results, $waitGroup): void {
                    $results[$key] = $batch->exec()['Test.Echo.value']->getResult();
                    $waitGroup->done();
                });
            }
            $waitGroup->wait();
        });

        self::assertCount(2, $results);
        self::assertSame('first', $results['first']);
        self::assertSame('second', $results['second']);
    }

    public function testRequestBatchRejectsChangesWhileExecuting(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), new class extends TcpTransport {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                return new Response($request->getRequestUUID());
            }
        });
        $batch = $client->buildRequest();
        $reported = null;
        $client->setOnException(static function (\Throwable $throwable) use (&$reported): void {
            $reported = $throwable;
        });
        $batch->addCall('Test.Echo.value')->setOnSuccess(function () use ($batch): void {
            $batch->addCall('Test.Echo.value');
        });

        self::assertCount(1, $this->execBatch($batch));
        self::assertInstanceOf(RpcException::class, $reported);
        self::assertStringContainsString('cannot be added', $reported->getMessage());
    }

    public function testRequestBatchRejectsCallsAfterExecutionCompletes(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), new class extends TcpTransport {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                return new Response($request->getRequestUUID());
            }
        });
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');
        $this->execBatch($batch);

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('cannot be added');
        $batch->addCall('Test.Echo.value');
    }

    public function testBusinessErrorDoesNotOfflineNode(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node'); $manager->online($node);
        $transport = new class extends TcpTransport {
            public int $calls = 0;

            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                $this->calls++;
                return new Response(
                    $request->getRequestUUID(),
                    status: ResponseStatus::SERVICE_ERROR,
                    msg: 'business failed',
                );
            }
        };
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->enableRetry(3);
        $response = $this->execBatch($batch)['Test.Echo.value'];
        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
        self::assertSame(NodeHealthStatus::HEALTHY, $response->getNodeHealthStatus());
        self::assertSame(1, $transport->calls);
        self::assertCount(1, $manager->getNodes('Test'));
    }

    public function testNetworkFailureOfflinesNodeOnlyAfterThreshold(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node'); $manager->online($node);
        $client = $this->client($manager, new ConsecutiveFailurePolicy(3), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response(
                        $request->getRequestUUID(),
                        status: ResponseStatus::TRANSPORT_ERROR,
                        nodeHealthStatus: NodeHealthStatus::SUSPECT,
                        msg: 'network down',
                    );
                }
            });

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $batch = $client->buildRequest();
            $batch->addCall('Test.Echo.value');
            self::assertSame(ResponseStatus::TRANSPORT_ERROR, $this->execBatch($batch)['Test.Echo.value']->getStatus());
            self::assertCount($attempt < 3 ? 1 : 0, $manager->getNodes('Test'));
        }
    }

    public function testPreservesFailureStatusReturnedByTransport(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node');
        $manager->online($node);
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response(
                        $request->getRequestUUID(),
                        status: ResponseStatus::SERVER_TIMEOUT,
                        nodeHealthStatus: NodeHealthStatus::SUSPECT,
                        msg: 'response timeout',
                    );
                }
            });

        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');
        $response = $this->execBatch($batch)['Test.Echo.value'];

        self::assertSame(ResponseStatus::SERVER_TIMEOUT, $response->getStatus());
        self::assertSame('response timeout', $response->getMsg());
    }

    public function testUnexpectedTransportThrowableUsesClientExceptionHandler(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = $this->client(
            $manager,
            new ConsecutiveFailurePolicy(),
            new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    throw new \LogicException('unexpected transport state');
                }
            },
        );
        $reported = null;
        $client->setOnException(static function (\Throwable $throwable) use (&$reported): void {
            $reported = $throwable;
        });
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');

        $responses = [];
        \Swoole\Coroutine\run(static function () use ($batch, &$responses): void {
            $responses = $batch->exec();
        });

        self::assertSame([], $responses);
        self::assertInstanceOf(\LogicException::class, $reported);
        self::assertSame('unexpected transport state', $reported->getMessage());
    }

    public function testBatchPreservesCallPathAfterHandledException(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $failure = new \LogicException('unexpected transport state');
        $client = $this->client(
            $manager,
            new ConsecutiveFailurePolicy(),
            new class($failure) extends TcpTransport {
                public function __construct(private readonly \LogicException $failure) {}

                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    if ($request->getArg() === 'first') {
                        throw $this->failure;
                    }
                    return new Response($request->getRequestUUID(), result: $request->getArg());
                }
            },
        );
        $reported = [];
        $client->setOnException(static function (\Throwable $throwable) use (&$reported): void {
            $reported[] = $throwable;
        });

        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.first')->setArg('first');
        $batch->addCall('Test.Echo.second')->setArg('second');
        $responses = $this->execBatch($batch);

        self::assertSame([$failure], $reported);
        self::assertSame(['Test.Echo.second'], array_keys($responses));
        self::assertSame('second', $responses['Test.Echo.second']->getResult());
    }

    public function testRequestEncodingFailureUsesClientExceptionHandler(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node', host: '127.0.0.1'));
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy());
        $reported = null;
        $client->setOnException(static function (\Throwable $throwable) use (&$reported): void {
            $reported = $throwable;
        });
        $batch = $client->buildRequest();
        $resource = fopen('php://memory', 'r');
        $batch->addCall('Test.Echo.value')->setArg($resource);

        try {
            self::assertSame([], $this->execBatch($batch));
        } finally {
            fclose($resource);
        }

        self::assertInstanceOf(RpcException::class, $reported);
        self::assertStringContainsString('encode RPC envelope', $reported->getMessage());
    }

    public function testRetriesShareOneActionDeadline(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $transport = new class extends TcpTransport {
            public array $timeouts = [];
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                $this->timeouts[] = $timeout;
                if (count($this->timeouts) === 1) {
                    \Swoole\Coroutine::sleep(0.02);
                }
                return new Response(
                    $request->getRequestUUID(),
                    status: ResponseStatus::TRANSPORT_ERROR,
                    nodeHealthStatus: NodeHealthStatus::SUSPECT,
                );
            }
        };
        $client = $this->client($manager, new ConsecutiveFailurePolicy(99), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->enableRetry(1);

        $this->execBatch($batch, 0.05);

        self::assertCount(2, $transport->timeouts);
        self::assertLessThan($transport->timeouts[0], $transport->timeouts[1]);
        self::assertLessThan(0.04, $transport->timeouts[1]);
    }

    public function testTcpTransportClassifiesOnlySocketTimeoutsAsConnectTimeout(): void
    {
        $classify = new \ReflectionMethod(TcpTransport::class, 'classifySocketFailure');

        self::assertSame(
            ResponseStatus::CONNECT_TIMEOUT,
            $classify->invoke(null, SOCKET_ETIMEDOUT, ResponseStatus::CONNECT_TIMEOUT),
        );
        self::assertSame(
            ResponseStatus::TRANSPORT_ERROR,
            $classify->invoke(null, SOCKET_ECONNREFUSED, ResponseStatus::CONNECT_TIMEOUT),
        );
    }

    public function testTcpTransportDoesNotTreatClosedConnectionAsServerTimeout(): void
    {
        $classify = new \ReflectionMethod(TcpTransport::class, 'classifySocketFailure');

        self::assertSame(
            ResponseStatus::SERVER_TIMEOUT,
            $classify->invoke(null, SOCKET_EAGAIN, ResponseStatus::SERVER_TIMEOUT),
        );
        self::assertSame(
            ResponseStatus::TRANSPORT_ERROR,
            $classify->invoke(null, SOCKET_ECONNRESET, ResponseStatus::SERVER_TIMEOUT),
        );
        self::assertSame(
            ResponseStatus::TRANSPORT_ERROR,
            $classify->invoke(null, 0, ResponseStatus::SERVER_TIMEOUT),
        );
    }

    public function testTcpTransportRejectsOversizedRequestBeforeConnecting(): void
    {
        $transport = new TcpTransport(config: new ProtocolConfig(maxPackageSize: 64));
        $request = new Request('Test.Echo.value', str_repeat('x', 128));

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('RPC request package exceeds the maximum size of 64 bytes.');

        $transport->send(new ServiceNode('Test', nodeId: 'node', host: '127.0.0.1'), $request, 1.0);
    }

    public function testTcpTransportCanCreateARealStreamSocketWithoutIpProtoTcpConstant(): void
    {
        $request = new Request('Test.Echo.value', requestUUID: 'request-id');
        $node = new ServiceNode('Test', 'node-1', host: '127.0.0.1', port: 1);

        $response = null;
        \Swoole\Coroutine\run(static function () use (&$response, $node, $request): void {
            $response = (new TcpTransport())->send($node, $request, 0.05);
        });

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(ResponseStatus::TRANSPORT_ERROR, $response->getStatus());
        self::assertSame('request-id', $response->getRequestUUID());
    }

    public function testRejectsMismatchedResponseUuid(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response('another-request');
                }
            });

        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');

        self::assertSame(ResponseStatus::RESPONSE_DECODE_ERROR, $this->execBatch($batch)['Test.Echo.value']->getStatus());
    }

    public function testRequestBatchCanOnlyBeExecutedOnceAfterHandledCallbackException(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response($request->getRequestUUID());
                }
            });
        $client->setOnException(static function (\Throwable $throwable): void {});
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->setOnSuccess(static function (): never {
            throw new \RuntimeException('callback failed');
        });

        self::assertCount(1, $this->execBatch($batch));

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('only be executed once');
        $this->execBatch($batch);
    }

    public function testBatchRejectsDuplicateCallPathImmediately(): void
    {
        $client = new Client(
            new SwooleTableNodeManager(16),
            new WeightedRandomNodeSelector(),
            new ConsecutiveFailurePolicy(),
        );
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->setArg('first');

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('RPC request path "Test.Echo.value" is already in this batch.');
        $batch->addCall('Test.Echo.value');
    }

    public function testAddRequestRejectsInvalidPathImmediately(): void
    {
        $client = new Client(
            new SwooleTableNodeManager(16),
            new WeightedRandomNodeSelector(),
            new ConsecutiveFailurePolicy(),
        );

        $this->expectException(RpcException::class);
        $client->buildRequest()->addCall('Test.action');
    }

    public function testExplicitOfflineHintImmediatelyRemovesNode(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node');
        $manager->online($node);
        $client = $this->client($manager, new ConsecutiveFailurePolicy(99), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response(
                        $request->getRequestUUID(),
                        status: ResponseStatus::SERVICE_ERROR,
                        nodeHealthStatus: NodeHealthStatus::OFFLINE,
                    );
                }
            });

        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');
        $response = $this->execBatch($batch)['Test.Echo.value'];

        self::assertSame(NodeHealthStatus::OFFLINE, $response->getNodeHealthStatus());
        self::assertSame([], $manager->getNodes('Test'));
    }

    public function testSuspectHintUsesConsecutiveFailureThreshold(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = $this->client($manager, new ConsecutiveFailurePolicy(2), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response(
                        $request->getRequestUUID(),
                        status: ResponseStatus::SERVICE_ERROR,
                        nodeHealthStatus: NodeHealthStatus::SUSPECT,
                    );
                }
            });

        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');
        $this->execBatch($batch);
        self::assertCount(1, $manager->getNodes('Test'));

        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');
        $this->execBatch($batch);
        self::assertSame([], $manager->getNodes('Test'));
    }

    private function client(
        SwooleTableNodeManager $manager,
        ConsecutiveFailurePolicy $failurePolicy,
        TcpTransport $transport,
    ): Client {
        $selector = new WeightedRandomNodeSelector();
        $client = new Client($manager, $selector, $failurePolicy);
        $executor = new RequestExecutor($manager, $selector, $failurePolicy, $transport);
        (new \ReflectionProperty($client, 'executor'))->setValue($client, $executor);
        return $client;
    }

    /** @return array<string, Response> */
    private function execBatch(RequestBatch $batch, float $timeout = 3.0): array
    {
        $responses = [];
        $error = null;
        \Swoole\Coroutine\run(static function () use ($batch, $timeout, &$responses, &$error): void {
            try {
                $responses = $batch->exec($timeout);
            } catch (\Throwable $throwable) {
                $error = $throwable;
            }
        });
        if ($error !== null) {
            throw $error;
        }
        return $responses;
    }
}
