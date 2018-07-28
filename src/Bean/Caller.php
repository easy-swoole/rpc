<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/27
 * Time: 下午10:24
 */

namespace EasySwoole\Rpc\Bean;


use EasySwoole\Spl\SplBean;

class Caller extends SplBean
{
    protected $client;
    protected $service;
    protected $action;
    protected $args = [];

    /**
     * @return mixed
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param mixed $client
     */
    public function setClient($client): void
    {
        $this->client = $client;
    }

    /**
     * @return mixed
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param mixed $service
     */
    public function setService($service): void
    {
        $this->service = $service;
    }

    /**
     * @return mixed
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param mixed $action
     */
    public function setAction($action): void
    {
        $this->action = $action;
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @param array $args
     */
    public function setArgs(array $args): void
    {
        $this->args = $args;
    }

    function setArg($key,$val)
    {
        $this->args[$key] = $val;
    }

    function getArg($key)
    {
        if(isset($this->args[$key])){
            return $this->args[$key];
        }
        return null;
    }

}