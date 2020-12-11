<?php


namespace EasySwoole\Rpc\Tests;


use EasySwoole\Rpc\Tests\Service\Service;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{

    function testServiceName()
    {
        $name = $this->prepareService()->serviceName();
        $this->assertEquals('ServiceOne',$name);
    }

    function testOnException()
    {

    }

    private function prepareService():Service
    {
        return new Service();
    }
}