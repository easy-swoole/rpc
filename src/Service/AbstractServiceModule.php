<?php


namespace EasySwoole\Rpc\Service;


use EasySwoole\Rpc\NetWork\Request;
use EasySwoole\Rpc\NetWork\Response;

abstract class AbstractServiceModule
{
    abstract function moduleName():string;

    protected function onRequest(Request $request):bool
    {
        return true;
    }

    protected function onException(\Throwable $throwable)
    {

    }

    protected function onActionNotFound(Request $request)
    {

    }

    public function __exec(Request $request,Response $response)
    {

    }
}