<?php


namespace EasySwoole\Rpc\Tests\Service;


use EasySwoole\Rpc\Service\AbstractServiceModule;

class ModuleTwo extends AbstractServiceModule
{
    function moduleName(): string
    {
        return 'ModuleTwo';
    }

    function args()
    {
        $this->response()->setResult($this->request()->getArg());
    }

    function action()
    {
        $this->response()->setMsg("hello action");
    }

    function exception()
    {
        throw new \Exception('the module exception');

    }

    protected function onException(\Throwable $throwable)
    {
        $this->response()->setStatus(-1)->setMsg($throwable->getMessage());
    }
}