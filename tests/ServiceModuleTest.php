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
        $ret = json_decode($this->buildClient('/?path=ServiceOne.ModuleNotFound')
            ->getBody(), true);
        $this->assertEquals(
            $this->buildResponse(['responseUUID' => $ret['responseUUID']])
                ->setStatus(\EasySwoole\Rpc\Protocol\Response::STATUS_MODULE_NOT_EXIST)
                ->jsonSerialize(),
            $ret
        );
    }

    function testActionNotFound()
    {
        $ret = json_decode($this->buildClient('/?path=ServiceOne.ModuleOne.notFound')
            ->getBody(), true);
        $this->assertEquals(
            $this->buildResponse(['responseUUID' => $ret['responseUUID']])
                ->setStatus(\EasySwoole\Rpc\Protocol\Response::STATUS_ACTION_NOT_EXIST)
                ->jsonSerialize(),
            $ret
        );
    }

    function testArgs()
    {
        $ret = json_decode($this->buildClient('/?path=ServiceOne.ModuleTwo.args&args=[1,2,3]')
            ->getBody(), true);
        $this->assertEquals(
            $this->buildResponse(['responseUUID' => $ret['responseUUID']])
                ->setStatus(\EasySwoole\Rpc\Protocol\Response::STATUS_OK)
                ->setResult("[1,2,3]")
                ->jsonSerialize(),
            $ret
        );
    }

    function testAction()
    {
        $ret = json_decode($this->buildClient('/?path=ServiceTwo.ModuleOne.action')
            ->getBody(), true);
        $this->assertEquals(
            $this->buildResponse(['responseUUID' => $ret['responseUUID']])
                ->setStatus(\EasySwoole\Rpc\Protocol\Response::STATUS_OK)
                ->setMsg('hello action')
                ->jsonSerialize(),
            $ret
        );
    }

    function testOnException()
    {
        $ret = json_decode($this->buildClient('/?path=ServiceOne.ModuleTwo.exception')
            ->getBody(), true);
        $this->assertEquals(
            $this->buildResponse(['responseUUID' => $ret['responseUUID']])
                ->setStatus(-1)
                ->setMsg('the module exception')
                ->jsonSerialize(),
            $ret
        );
    }

    private function buildClient($path = '/')
    {
        $list = [9501, 9502];
        $port = $list[array_rand($list,1)];
        $client = new \Swoole\Coroutine\Http\Client('127.0.0.1', $port);
        $client->get($path);
        return $client;
    }

    private function buildResponse($data = []): Response
    {
        return new Response($data);
    }
}