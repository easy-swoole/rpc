<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Client\Client;
use EasySwoole\Rpc\AbstractInterface\TransportInterface;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Utility\MemoryNodeManager;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testExecutesRequestAndCallbackThroughInjectedTransport(): void
    {
        $manager = new MemoryNodeManager();
        $node = new ServiceNode('Test', nodeId: 'node'); $manager->online($node);
        $transport = new class implements TransportInterface {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            { return (new Response($request->getRequestUUID()))->setResult($request->getArg()); }
        };
        $called = false;
        $client = (new Client($manager))->setTransport($transport);
        $client->addRequest('Test.echo')->setArg('value')->setOnSuccess(function (Response $response) use (&$called): void {
            $called = $response->getResult() === 'value';
        });
        $responses = $client->exec();
        self::assertTrue($called);
        self::assertSame('value', $responses[0]->getResult());
    }

    public function testReportsMissingNodeWithoutCallingTransport(): void
    {
        $client = new Client(new MemoryNodeManager());
        $client->setTransport(new class implements TransportInterface {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            { throw new \LogicException('must not be called'); }
        });
        $client->addRequest('Missing.action');
        self::assertSame(Response::STATUS_NOT_AVAILABLE_NODE, $client->exec()[0]->getStatus());
    }

    public function testRetryOfflinesFailedNodeAndSelectsAnotherNode(): void
    {
        $manager = new MemoryNodeManager();
        $failed = new ServiceNode('Test', nodeId: 'failed');
        $healthy = new ServiceNode('Test', nodeId: 'healthy');
        $manager->online($failed); $manager->online($healthy);
        $transport = new class implements TransportInterface {
            public array $visited = [];
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                $this->visited[] = $node->getNodeId();
                if ($node->getNodeId() === 'failed') { throw new \RuntimeException('down'); }
                return (new Response($request->getRequestUUID()))->setResult('ok');
            }
        };
        $client = (new Client($manager))->setTransport($transport);
        $client->addRequest('Test.echo')->setServiceNode($failed)->enableRetry(1);
        self::assertTrue($client->exec()[0]->isSuccess());
        self::assertSame(['failed', 'healthy'], $transport->visited);
        self::assertSame(['healthy'], array_map(static fn (ServiceNode $node): string => $node->getNodeId(), $manager->getNodes('Test')));
    }

    public function testExecutesQueuedRequestsConcurrently(): void
    {
        $manager = new MemoryNodeManager();
        $node = new ServiceNode('Test', nodeId: 'node'); $manager->online($node);
        $client = (new Client($manager))->setTransport(new class implements TransportInterface {
            public function send(ServiceNode $node, Request $request, float $timeout): Response
            {
                \Swoole\Coroutine::sleep(0.05);
                return new Response($request->getRequestUUID());
            }
        });
        $client->addRequest('Test.echo'); $client->addRequest('Test.echo');
        $start = microtime(true);
        self::assertCount(2, $client->exec());
        self::assertLessThan(0.09, microtime(true) - $start);
    }
}
