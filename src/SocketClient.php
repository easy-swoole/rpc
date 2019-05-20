<?php


namespace EasySwoole\Rpc;


class SocketClient
{
    protected $socket;

    /**
     * @return mixed
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @param mixed $socket
     */
    public function setSocket($socket): void
    {
        $this->socket = $socket;
    }

}