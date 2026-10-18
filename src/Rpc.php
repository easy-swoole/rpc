<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\Client\Client;
use EasySwoole\Rpc\Client\TcpTransport;
use EasySwoole\Rpc\Server\Server;
use EasySwoole\Rpc\Server\ServerConfig;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;

class Rpc
{
    private Server $server;

    private AbstractNodeManager $nodeManager;

    public function __construct(
        ?AbstractNodeManager $nodeManager = null,
        ?ServerConfig $serverConfig = null,
    )
    {
        $this->nodeManager = $nodeManager ?? new SwooleTableNodeManager();
        $this->server = new Server($this->nodeManager, $serverConfig ?? new ServerConfig());
    }

    public function getNodeManager(): AbstractNodeManager
    {
        return $this->nodeManager;
    }

    public function server(): Server
    {
        return $this->server;
    }

    public function client(): Client
    {
        return (new Client($this->getNodeManager()))->setTransport(new TcpTransport());
    }

}
