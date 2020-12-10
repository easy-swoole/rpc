<?php


namespace EasySwoole\Rpc\Service;


abstract class AbstractService
{
    private $modules = [];

    abstract function serviceName():string;
    abstract function serviceVersion():int;

    function addModule(AbstractServiceModule $module):AbstractService
    {
        $this->modules[$module->moduleName()] = $module;
        return $this;
    }
}