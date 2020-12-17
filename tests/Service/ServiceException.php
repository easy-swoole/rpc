<?php


namespace EasySwoole\Rpc\Tests\Service;


use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Service\AbstractService;

class ServiceException extends AbstractService
{
    function serviceName(): string
    {
        return 'ServiceException';
    }

    protected function onRequest(Request $request): bool
    {
        throw new \Exception('the service exception');
    }

    protected function onException(\Throwable $throwable)
    {
        $this->response()->setStatus(-1)->setMsg($throwable->getMessage());
    }
}