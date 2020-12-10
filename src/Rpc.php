<?php


namespace EasySwoole\Rpc;


use EasySwoole\Rpc\Service\AbstractService;

class Rpc
{
    private $config;

    function __construct(Config $config)
    {
        $this->config = $config;
    }

    private $service = [];

    function addService(AbstractService $service):Rpc
    {
        $this->service[$service->serviceName()] = $service;
        return $this;
    }
}