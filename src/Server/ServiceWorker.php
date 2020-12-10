<?php


namespace EasySwoole\Rpc\Server;


use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use Swoole\Coroutine\Socket;

class ServiceWorker extends AbstractTcpProcess
{
    function onAccept(Socket $socket)
    {
        // TODO: Implement onAccept() method.
    }
}