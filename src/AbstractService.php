<?php


namespace EasySwoole\Rpc;


abstract class AbstractService
{
    /*
     * 禁止重写该方法，防止在构造函数中抛出异常
     */
    final public function __construct()
    {
    }

    abstract protected function onRequest():bool ;
    abstract protected function afterAction();
    abstract public function serviceName():string ;
    /*
     * 每秒会执行一次，请自己实现间隔需求
     */
    abstract public function onTick();

    protected function onException(\Throwable $throwable)
    {
        throw $throwable;
    }

    public function version()
    {
        return 1.0;
    }

    public function __hook()
    {

    }

}