<?php


namespace EasySwoole\Rpc\Tests\Service;


use EasySwoole\Rpc\Service\AbstractService;

class ServiceTwo extends AbstractService
{
    function serviceName(): string
    {
        return 'ServiceTwo';
    }
}