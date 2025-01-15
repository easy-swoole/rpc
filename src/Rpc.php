<?php

namespace EasySwoole\Rpc;

use EasySwoole\Component\Singleton;
use EasySwoole\Rpc\Exception\Runtime;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\Utility\AbstractNodeManager;

class Rpc
{
    use Singleton;

    private Server $server;

    private AbstractNodeManager $nodeManager;

    function setNodeManager(AbstractNodeManager $nodeManager):Rpc
    {
        $this->nodeManager = $nodeManager;
        return $this;
    }

    function getNodeManager():AbstractNodeManager
    {
        if(!isset($this->nodeManager)){
            throw new Runtime("please set nodeManager first");
        }
        return $this->nodeManager;
    }

    function server():Server
    {
        if(isset($this->server)){
            return $this->server;
        }
        if(!isset($this->nodeManager)){
            throw new Runtime("please set nodeManager first");
        }
        $this->server = new Server($this->nodeManager);
        return $this->server;
    }

    function client()
    {

    }

}