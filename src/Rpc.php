<?php


namespace EasySwoole\Rpc;


use EasySwoole\Rpc\Server\AssistWorker;
use EasySwoole\Rpc\Service\AbstractService;
use Swoole\Server;

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

    function client():Client
    {
        return new Client($this->config->getClient());
    }

    function attachServer(Server $server)
    {

    }

    function __getServiceWorker():array
    {

    }

    function __getAssistWorker():AssistWorker
    {

    }
}