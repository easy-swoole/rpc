<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/27
 * Time: 下午10:26
 */

namespace EasySwoole\Rpc\Bean;


use EasySwoole\Spl\SplArray;

class Client extends SplArray
{
    protected $fd;
    protected $reactorId;
    protected $ip;

    /**
     * @return mixed
     */
    public function getFd()
    {
        return $this->fd;
    }

    /**
     * @param mixed $fd
     */
    public function setFd($fd): void
    {
        $this->fd = $fd;
    }

    /**
     * @return mixed
     */
    public function getReactorId()
    {
        return $this->reactorId;
    }

    /**
     * @param mixed $reactorId
     */
    public function setReactorId($reactorId): void
    {
        $this->reactorId = $reactorId;
    }

    /**
     * @return mixed
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param mixed $ip
     */
    public function setIp($ip): void
    {
        $this->ip = $ip;
    }
}