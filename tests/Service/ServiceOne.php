<?php


namespace EasySwoole\Rpc\Tests\Service;


use EasySwoole\Rpc\Service\AbstractService;

class ServiceOne extends AbstractService
{
    function serviceName(): string
    {
        return 'ServiceOne';
    }
}