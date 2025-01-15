<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Utility\AbstractNodeManager;
use EasySwoole\Utility\Random;

class Server
{
    private array $service = [];

    private ServerNode $node;

    private AbstractNodeManager $nodeManager;

    function __construct(AbstractNodeManager $nodeManager)
    {
        $this->nodeManager = $nodeManager;
        $this->node = new ServerNode();
        $this->node->setNodeId(Random::makeUUIDV4());
    }

    function registerService(AbstractService $service):void
    {
        $service->__init($this->node,$this->nodeManager);
        $this->service[$service->serviceName()] = $service;
    }

    function getNode():ServerNode
    {
        return $this->node;
    }

    function allService():array
    {
        return $this->service;
    }

    function getService(string $serviceName):?AbstractService
    {
        if(isset($this->service[$serviceName])){
            return $this->service[$serviceName];
        }
        return null;
    }
}