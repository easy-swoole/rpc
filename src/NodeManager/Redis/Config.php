<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-28
 * Time: 08:58
 */

namespace EasySwoole\Rpc\NodeManager\Redis;


use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    protected $host;
    protected $auth;
    protected $port;

    /**
     * @return mixed
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param mixed $host
     */
    public function setHost($host): void
    {
        $this->host = $host;
    }

    /**
     * @return mixed
     */
    public function getAuth()
    {
        return $this->auth;
    }

    /**
     * @param mixed $auth
     */
    public function setAuth($auth): void
    {
        $this->auth = $auth;
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