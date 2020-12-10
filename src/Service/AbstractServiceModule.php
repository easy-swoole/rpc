<?php


namespace EasySwoole\Rpc\Service;


use EasySwoole\Rpc\NetWork\Request;
use EasySwoole\Rpc\NetWork\Response;

abstract class AbstractServiceModule
{
    /** @var Request */
    private $request;
    /** @var Response */
    private $response;

    abstract function moduleName(): string;

    protected function request(): Request
    {
        return $this->request;
    }

    protected function response(): Response
    {
        return $this->response;
    }

    protected function onRequest(Request $request): bool
    {
        return true;
    }

    protected function onException(\Throwable $throwable)
    {

    }

    protected function onActionNotFound(Request $request)
    {

    }

    public function __exec(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
        try {
            if ($this->onRequest($request) !== false) {
                $action = $request->getAction();
                if (method_exists(static::class, $action)) {
                    $this->$action();
                } else {
                    $this->onActionNotFound($request);
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