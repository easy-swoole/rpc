<?php

namespace EasySwoole\Rpc\Utility;

use EasySwoole\Rpc\Server\ServerNode;

abstract class AbstractNodeManager
{
    abstract function heartbeat(ServerNode $serverNode,string $serviceName):bool;
    abstract function offline(ServerNode $serverNode,string $serviceName):bool;
    abstract function online(ServerNode $serverNode,string $serviceName):bool;
    abstract function hibernate(ServerNode $serverNode,string $serviceName):bool;
}