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
    abstract protected function ServiceName():string ;

    protected function onException(\Throwable $throwable)
    {
        throw $throwable;
    }

}