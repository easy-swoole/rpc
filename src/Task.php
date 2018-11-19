<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/12
 * Time: 9:42 PM
 */

namespace EasySwoole\Rpc;


class Task
{
    private $action;
    private $arg = [];
    private $onSuccess;
    private $onFail;
    private $timeout = 0.5;

    public function callAction(string $action): Task
    {
        $this->action = $action;
        return $this;
    }

    function setArg(array $arg):Task
    {
        $this->arg = $arg;
        return $this;
    }

    function onSuccess(callable $call = null):Task
    {
        $this->onSuccess = $call;
        return $this;
    }

    function onFail(callable $call = null):Task
    {
        $this->onFail = $call;
        return $this;
    }

    function setTimeout(float $timeout):Task
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function __getAction():?string
    {
        return $this->action;
    }

    /**
     * @return array
     */
    public function __getArg():?array
    {
        return $this->arg;
    }

    /**
     * @return callable
     */
    public function __getOnSuccess():?callable
    {
        return $this->onSuccess;
    }

    /**
     * @return mixed
     */
    public function __getOnFail():?callable
    {
        return $this->onFail;
    }

    /**
     * @return float
     */
    public function __getTimeout(): float
    {
        return $this->timeout;
    }


}