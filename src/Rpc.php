<?php


namespace EasySwoole\Rpc;


use EasySwoole\Rpc\Service\AbstractService;

class Rpc
{
    private $service = [];

    function addService(AbstractService $service):Rpc
    {
        $this->service[$service->serviceName()] = $service;
        return $this;
    }
}