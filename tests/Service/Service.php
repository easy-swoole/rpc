<?php


namespace EasySwoole\Rpc\Tests\Service;


use EasySwoole\Rpc\Service\AbstractService;

class Service extends AbstractService
{
    function serviceName(): string
    {
        return 'Service';
    }
}