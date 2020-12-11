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

    /** @var array $allowMethodReflections */
    protected $allowMethodReflections = [];

    public function __construct()
    {
        $forbidList = [
            '__hook', '__exec', '__destruct',
            '__clone', '__construct', '__call',
            '__callStatic', '__get', '__set',
            '__isset', '__unset', '__sleep',
            '__wakeup', '__toString', '__invoke',
            '__set_state', '__clone', '__debugInfo',
            'onRequest'
        ];

        $refClass = new \ReflectionClass(static::class);
        $refMethods = $refClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($refMethods as $refMethod) {
            if ((!in_array($refMethod->getName(), $forbidList)) && (!$refMethod->isStatic())) {
                $this->allowMethodReflections[$refMethod->getName()] = $refMethod;
            }
        }
    }

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
                if (isset($this->allowMethodReflections[$action])) {
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