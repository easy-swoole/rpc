<?php


namespace EasySwoole\Rpc\Service;


use EasySwoole\Rpc\Network\Request;
use EasySwoole\Rpc\Network\Response;
use Swoole\Coroutine\Socket;

abstract class AbstractService
{
    private $modules = [];
    /** @var Request */
    private $request;
    /** @var Response */
    private $response;
    /** @var Socket */
    private $socket;

    abstract function serviceName(): string;

    protected function request(): Request
    {
        return $this->request;
    }

    protected function response(): Response
    {
        return $this->response;
    }

    function serviceVersion(): int
    {
        return 1;
    }

    function addModule(AbstractServiceModule $module): AbstractService
    {
        $this->modules[$module->moduleName()] = $module;
        return $this;
    }

    protected function onRequest(Request $request): bool
    {
        return true;
    }

    protected function onException(\Throwable $throwable)
    {

    }

    protected function moduleNotFound(Request $request)
    {

    }

    protected function getSocket():Socket
    {
        return $this->socket;
    }

    public function __exec(Request $request, Response $response,Socket $socket)
    {
        $this->request = $request;
        $this->response = $response;
        $this->socket = $socket;
        try {
            if ($this->onRequest($request) !== false) {
                $module = $this->modules[$request->getModule()] ?? null;
                if ($module && $module instanceof AbstractServiceModule) {
                    //克隆模式，否则如果定义了成员属性会发生协程污染
                    $module = clone $module;
                    $module->__exec($request, $response,$socket);
                } else {
                    $this->moduleNotFound($request);
                }
            }
        } catch (\Throwable $throwable) {
            $this->onException($throwable);
        } finally {
            try {
                $this->afterRequest($request);
            } catch (\Throwable $throwable) {
                $this->onException($throwable);
            }
        }
    }

    protected function afterRequest(Request $request)
    {

    }
}