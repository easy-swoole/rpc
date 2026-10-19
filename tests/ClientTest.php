<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Client\Client;
use EasySwoole\Rpc\Client\TransportException;
use EasySwoole\Rpc\Client\TcpTransport;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Exception\RpcException;
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
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(), $transport);
        $client->addRequest('Test.Echo.value')->setArg('value')->setOnSuccess(function (Response $response) use (&$called): void {
            $called = $response->getResult() === 'value';
        });
        $responses = $client->exec();
        self::assertTrue($called);
        self::assertSame('value', $responses[0]->getResult());
    }

    public function testReportsMissingNodeWithoutCallingTransport(): void
    {
        $client = new Client(new SwooleTableNodeManager(16), new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(), new class extends TcpTransport {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            { throw new \LogicException('must not be called'); }
        });
        $client->addRequest('Missing.Module.action');
        self::assertSame(ResponseStatus::NOT_AVAILABLE_NODE, $client->exec()[0]->getStatus());
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
                if ($node->getNodeId() === 'failed') { throw new \RuntimeException('down'); }
                return (new Response($request->getRequestUUID()))->setResult('ok');
            }
        };
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(1), $transport);
        $client->addRequest('Test.Echo.value')->setServiceNode($failed)->enableRetry(1);
        self::assertTrue($client->exec()[0]->isSuccess());
        self::assertSame(['failed', 'healthy'], $transport->visited);
        self::assertSame(['healthy'], array_map(static fn (ServiceNode $node): string => $node->getNodeId(), $manager->getNodes('Test')));
    }

    public function testExecutesQueuedRequestsConcurrently(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node'); $manager->online($node);
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(), new class extends TcpTransport {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                \Swoole\Coroutine::sleep(0.05);
                return new Response($request->getRequestUUID());
            }
        });
        $client->addRequest('Test.Echo.value'); $client->addRequest('Test.Echo.value');
        $start = microtime(true);
        self::assertCount(2, $client->exec());
        self::assertLessThan(0.09, microtime(true) - $start);
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
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(), $transport);
        $client->addRequest('Test.Echo.value')->enableRetry(3);
        $response = $client->exec()[0];
        self::assertSame(ResponseStatus::SERVICE_ERROR, $response->getStatus());
        self::assertSame(NodeHealthStatus::HEALTHY, $response->getNodeHealthStatus());
        self::assertSame(1, $transport->calls);
        self::assertCount(1, $manager->getNodes('Test'));
    }

    public function testNetworkFailureOfflinesNodeOnlyAfterThreshold(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node'); $manager->online($node);
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(3), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    throw new \RuntimeException('network down');
                }
            });

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $client->addRequest('Test.Echo.value');
            self::assertSame(ResponseStatus::TRANSPORT_ERROR, $client->exec()[0]->getStatus());
            self::assertCount($attempt < 3 ? 1 : 0, $manager->getNodes('Test'));
        }
    }

    public function testPreservesStatusFromTransportException(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node');
        $manager->online($node);
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    throw new TransportException(ResponseStatus::SERVER_TIMEOUT, 'response timeout');
                }
            });

        $client->addRequest('Test.Echo.value');
        $response = $client->exec()[0];

        self::assertSame(ResponseStatus::SERVER_TIMEOUT, $response->getStatus());
        self::assertSame('response timeout', $response->getMsg());
    }

    public function testRejectsMismatchedResponseUuid(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response('another-request');
                }
            });

        $client->addRequest('Test.Echo.value');

        self::assertSame(ResponseStatus::RESPONSE_DECODE_ERROR, $client->exec()[0]->getStatus());
    }

    public function testCoroutineExceptionIsRethrownAndRequestQueueIsCleared(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return new Response($request->getRequestUUID());
                }
            });
        $client->addRequest('Test.Echo.value')->setOnSuccess(static function (): never {
            throw new \RuntimeException('callback failed');
        });

        try {
            $client->exec();
            self::fail('Callback exceptions must be rethrown after all coroutines finish.');
        } catch (\RuntimeException $exception) {
            self::assertSame('callback failed', $exception->getMessage());
        }
        self::assertSame([], $client->exec());
    }

    public function testAddRequestRejectsInvalidPathImmediately(): void
    {
        $client = new Client(new SwooleTableNodeManager(16), new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(), new TcpTransport());

        $this->expectException(RpcException::class);
        $client->addRequest('Test.action');
    }

    public function testExplicitOfflineHintImmediatelyRemovesNode(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $node = new ServiceNode('Test', nodeId: 'node');
        $manager->online($node);
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(99), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return (new Response($request->getRequestUUID()))
                        ->setStatus(ResponseStatus::SERVICE_ERROR)
                        ->setNodeHealthStatus(NodeHealthStatus::OFFLINE);
                }
            });

        $client->addRequest('Test.Echo.value');
        $response = $client->exec()[0];

        self::assertSame(NodeHealthStatus::OFFLINE, $response->getNodeHealthStatus());
        self::assertSame([], $manager->getNodes('Test'));
    }

    public function testSuspectHintUsesConsecutiveFailureThreshold(): void
    {
        $manager = new SwooleTableNodeManager(16);
        $manager->online(new ServiceNode('Test', nodeId: 'node'));
        $client = new Client($manager, new WeightedRandomNodeSelector(), new ConsecutiveFailurePolicy(2), new class extends TcpTransport {
                public function send(ServiceNode $node, Request $request, float $timeout): Response
                {
                    return (new Response($request->getRequestUUID()))
                        ->setStatus(ResponseStatus::SERVICE_ERROR)
                        ->setNodeHealthStatus(NodeHealthStatus::SUSPECT);
                }
            });

        $client->addRequest('Test.Echo.value');
        $client->exec();
        self::assertCount(1, $manager->getNodes('Test'));

        $client->addRequest('Test.Echo.value');
        $client->exec();
        self::assertSame([], $manager->getNodes('Test'));
    }
}
