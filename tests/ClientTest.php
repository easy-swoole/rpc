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
use EasySwoole\Rpc\Protocol\ProtocolConfig;
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
            { return (new Response($request->getRequestUUID()))->setResult($request->getArg()); }
        };
        $called = false;
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->setArg('value')->setOnSuccess(function (Response $response) use (&$called): void {
            $called = $response->getResult() === 'value';
        });
        $responses = $this->execBatch($batch);
        self::assertTrue($called);
        self::assertSame('value', $responses[0]->getResult());
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
        $response = $this->execBatch($batch)[0];
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
        foreach (['first callback failed', 'second callback failed'] as $message) {
            $batch->addCall('Test.Echo.value')->setOnSuccess(static function () use ($message): never {
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

    public function testNodeRegistryQueryFailureReturnsUnavailableResponse(): void
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
        $batch->addCall('Test.Echo.value');

        $responses = [];
        \Swoole\Coroutine\run(static function () use ($batch, &$responses): void {
            $responses = $batch->exec();
        });

        self::assertCount(1, $responses);
        self::assertSame(ResponseStatus::NOT_AVAILABLE_NODE, $responses[0]->getStatus());
        self::assertSame('', $responses[0]->getRequestUUID());
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
                    return (new Response($request->getRequestUUID()))
                        ->setStatus(ResponseStatus::TRANSPORT_ERROR)
                        ->setNodeHealthStatus(NodeHealthStatus::SUSPECT)
                        ->setMsg('down');
                }
                return (new Response($request->getRequestUUID()))->setResult('ok');
            }
        };
        $client = $this->client($manager, new ConsecutiveFailurePolicy(1), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->setServiceNode($failed)->enableRetry(1);
        self::assertTrue($this->execBatch($batch)[0]->isSuccess());
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
                    return (new Response($request->getRequestUUID()))
                        ->setStatus(ResponseStatus::TRANSPORT_ERROR)
                        ->setNodeHealthStatus(NodeHealthStatus::SUSPECT);
                }
                return (new Response($request->getRequestUUID()))->setResult('ok');
            }
        };
        $client = $this->client($manager, new ConsecutiveFailurePolicy(99), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->setServiceNode($failed)->enableRetry(1);

        self::assertTrue($this->execBatch($batch)[0]->isSuccess());
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
                return (new Response($request->getRequestUUID()))
                    ->setStatus(ResponseStatus::TRANSPORT_ERROR)
                    ->setNodeHealthStatus(NodeHealthStatus::SUSPECT);
            }
        };
        $client = $this->client($manager, new ConsecutiveFailurePolicy(99), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->enableRetry(1);

        self::assertSame(ResponseStatus::TRANSPORT_ERROR, $this->execBatch($batch)[0]->getStatus());
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
                return (new Response($request->getRequestUUID()))
                    ->setStatus(ResponseStatus::TRANSPORT_ERROR)
                    ->setNodeHealthStatus(NodeHealthStatus::SUSPECT);
            }
        });
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->enableRetry(1);

        $response = $this->execBatch($batch)[0];
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
        $batch->addCall('Test.Echo.value'); $batch->addCall('Test.Echo.value');
        $start = microtime(true);
        self::assertCount(2, $this->execBatch($batch));
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
                return (new Response($request->getRequestUUID()))->setResult($request->getArg());
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
                    $results[$key] = $batch->exec()[0]->getResult();
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
                return (new Response($request->getRequestUUID()))
                    ->setStatus(ResponseStatus::SERVICE_ERROR)
                    ->setMsg('business failed');
            }
        };
        $client = $this->client($manager, new ConsecutiveFailurePolicy(), $transport);
        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value')->enableRetry(3);
        $response = $this->execBatch($batch)[0];
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
                    return (new Response($request->getRequestUUID()))
                        ->setStatus(ResponseStatus::TRANSPORT_ERROR)
                        ->setNodeHealthStatus(NodeHealthStatus::SUSPECT)
                        ->setMsg('network down');
                }
            });

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $batch = $client->buildRequest();
            $batch->addCall('Test.Echo.value');
            self::assertSame(ResponseStatus::TRANSPORT_ERROR, $this->execBatch($batch)[0]->getStatus());
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
                    return (new Response($request->getRequestUUID()))
                        ->setStatus(ResponseStatus::SERVER_TIMEOUT)
                        ->setNodeHealthStatus(NodeHealthStatus::SUSPECT)
                        ->setMsg('response timeout');
                }
            });

        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');
        $response = $this->execBatch($batch)[0];

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

        $response = $transport->send(new ServiceNode('Test', nodeId: 'node', ip: '127.0.0.1'), $request, 1.0);

        self::assertSame(ResponseStatus::TRANSPORT_ERROR, $response->getStatus());
        self::assertSame(NodeHealthStatus::SUSPECT, $response->getNodeHealthStatus());
        self::assertStringContainsString('maximum size', $response->getMsg());
    }

    public function testTcpTransportCanCreateARealStreamSocketWithoutIpProtoTcpConstant(): void
    {
        $request = new Request('Test.Echo.value', requestUUID: 'request-id');
        $node = new ServiceNode('Test', 'node-1', ip: '127.0.0.1', port: 1);

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

        self::assertSame(ResponseStatus::RESPONSE_DECODE_ERROR, $this->execBatch($batch)[0]->getStatus());
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
                    return (new Response($request->getRequestUUID()))
                        ->setStatus(ResponseStatus::SERVICE_ERROR)
                        ->setNodeHealthStatus(NodeHealthStatus::OFFLINE);
                }
            });

        $batch = $client->buildRequest();
        $batch->addCall('Test.Echo.value');
        $response = $this->execBatch($batch)[0];

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
                    return (new Response($request->getRequestUUID()))
                        ->setStatus(ResponseStatus::SERVICE_ERROR)
                        ->setNodeHealthStatus(NodeHealthStatus::SUSPECT);
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

    /** @return list<Response> */
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
