<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/12
 * Time: 10:33 PM
 */

namespace EasySwoole\Rpc;


class ServiceNode
{
    protected $ip;
    protected $port;

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

    /**
     * @return mixed
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param mixed $port
     */
    public function setPort($port): void
    {
        $this->port = $port;
    }

}