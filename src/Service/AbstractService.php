<?php


namespace EasySwoole\Rpc\Service;


use EasySwoole\Rpc\NetWork\Request;
use EasySwoole\Rpc\NetWork\Response;

abstract class AbstractService
{
    private $modules = [];

    abstract function serviceName():string;

    function serviceVersion(): int
    {
        return 1;
    }

    function addModule(AbstractServiceModule $module):AbstractService
    {
        $this->modules[$module->moduleName()] = $module;
        return $this;
    }

    protected function onRequest(Request $request):bool
    {
        return true;
    }

    protected function onException(\Throwable $throwable)
    {

    }

    protected function onModuleNotFound(Request $request)
    {

    }

    public function __exec(Request $request,Response $response)
    {

    }
}