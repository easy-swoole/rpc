<?php


namespace EasySwoole\Rpc\Tests;


use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Tests\Service\ModuleOne;
use EasySwoole\Rpc\Tests\Service\ModuleTwo;
use PHPUnit\Framework\TestCase;

class ServiceModuleTest extends TestCase
{
    function testServiceModuleName()
    {
        $this->assertEquals('ModuleOne', (new ModuleOne())->moduleName());
        $this->assertEquals('ModuleTwo', (new ModuleTwo())->moduleName());
    }

    function testModuleNotFound()
    {
        $this->assertEquals(
            $this->buildResponse()
                ->setStatus(\EasySwoole\Rpc\Protocol\Response::STATUS_MODULE_NOT_EXIST)
                ->__toString(),
            $this->buildClient('/?path=ServiceOne.ModuleNotFound')
                ->getBody()
        );
    }

    function testActionNotFound()
    {
        $this->assertEquals(
            $this->buildResponse()
                ->setStatus(\EasySwoole\Rpc\Protocol\Response::STATUS_ACTION_NOT_EXIST)
                ->__toString(),
            $this->buildClient('/?path=ServiceTwo.ModuleOne.notFound')
                ->getBody()
        );
    }

    function testArgs()
    {
        $this->assertEquals(
            $this->buildResponse()
                ->setStatus(0)
                ->setResult("[1,2,3]")
                ->__toString(),
            $this->buildClient('/?path=ServiceOne.ModuleTwo.args&args=[1,2,3]')
                ->getBody()
        );
    }

    function testAction()
    {
        $this->assertEquals(
            $this->buildResponse()
                ->setStatus(0)
                ->setMsg('hello action')
                ->__toString(),
            $this->buildClient('/?path=ServiceTwo.ModuleOne.action')
                ->getBody()
        );
    }

    function testOnException()
    {
        $this->assertEquals(
            $this->buildResponse()
                ->setStatus(-1)
                ->setMsg('the module exception')
                ->__toString(),
            $this->buildClient('/?path=ServiceOne.ModuleTwo.exception')
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
}