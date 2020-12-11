<?php


namespace EasySwoole\Rpc\Tests\Service;


use EasySwoole\Rpc\Service\AbstractServiceModule;

class Module extends AbstractServiceModule
{
    function moduleName(): string
    {
        return 'Module';
    }

    function action()
    {
        $this->response()->setMsg("action");
    }
}