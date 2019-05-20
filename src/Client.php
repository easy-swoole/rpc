<?php


namespace EasySwoole\Rpc;


class Client
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