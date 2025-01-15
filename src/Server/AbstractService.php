<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Utility\AbstractNodeManager;

abstract class AbstractService
{

    private ServerNode $serverNode;
    private AbstractNodeManager $nodeManager;

    abstract function serviceName():string;

    public function __init(
         ServerNode $serverNode,
         AbstractNodeManager $nodeManager
    )
    {
        $this->serverNode = $serverNode;
        $this->nodeManager = $nodeManager;
    }

    function heartbeat():bool
    {
        return $this->nodeManager->heartbeat($this->serverNode,$this->serviceName());
    }

    function offline():bool
    {
        return $this->nodeManager->offline($this->serverNode,$this->serviceName());
    }

    function online():bool
    {
        return $this->nodeManager->online($this->serverNode,$this->serviceName());
    }

    function hibernate():bool
    {
        return $this->nodeManager->hibernate($this->serverNode,$this->serviceName());
    }
}