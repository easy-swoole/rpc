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
        $this->assertEquals(
            $this->buildResponse()
                ->setStatus(\EasySwoole\Rpc\Protocol\Response::STATUS_NOT_AVAILABLE_NODE)
                ->__toString(),
            $this->buildClient('/?path=ServiceNotFound')
                ->getBody()
        );
    }


    function testOnException()
    {
        $this->assertEquals(
            $this->buildResponse()
                ->setStatus(-1)
                ->setMsg('the service exception')
                ->__toString(),
            $this->buildClient('/?path=ServiceException')
                ->getBody()
        );
    }

    private function buildClient($path = '/')
    {
        $client = new \Swoole\Coroutine\Http\Client('127.0.0.1', 9501);
        $client->get($path);
        return $client;
    }

    private function buildResponse(): Response
    {
        return new Response();
    }

    private function prepareService(): ServiceOne
    {
        return new ServiceOne();
    }
}