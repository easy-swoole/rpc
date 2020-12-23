<?php


namespace EasySwoole\Rpc\Tests;


use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Tests\Service\ServiceOne;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{

    function testServiceName()
    {
        $name = $this->prepareService()->serviceName();
        $this->assertEquals('ServiceOne', $name);
    }

    function testNotFound()
    {
        $ret = json_decode($this->buildClient('/?path=ServiceNotFound')
            ->getBody(), true);
        $this->assertEquals(
            $this->buildResponse(['responseUUID' => $ret['responseUUID']])
                ->setStatus(\EasySwoole\Rpc\Protocol\Response::STATUS_NOT_AVAILABLE_NODE)
                ->jsonSerialize(),
            $ret
        );
    }


    function testOnException()
    {
        $ret = json_decode($this->buildClient('/?path=ServiceException')
            ->getBody(), true);
        $this->assertEquals(
            $this->buildResponse(['responseUUID' => $ret['responseUUID']])
                ->setStatus(-1)
                ->setMsg('the service exception')
                ->jsonSerialize(),
            $ret
        );
    }

    private function buildClient($path = '/')
    {
        $client = new \Swoole\Coroutine\Http\Client('127.0.0.1', 9501);
        $client->get($path);
        return $client;
    }

    private function buildResponse($data = []): Response
    {
        return new Response($data);
    }

    private function prepareService(): ServiceOne
    {
        return new ServiceOne();
    }
}