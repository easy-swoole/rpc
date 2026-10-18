<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Attribute\RpcAction;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;

abstract class AbstractServiceModule
{
    abstract public function moduleName(): string;

    final public function __dispatch(Request $request, Response $response): Response
    {
        try {
            if (!$this->onRequest($request)) {
                return $response->setStatus(Response::STATUS_SERVICE_SHUTDOWN);
            }
            $action = $request->getAction();
            if (!$this->isActionAllowed($action)) {
                return $this->actionNotFound($request, $response);
            }
            $result = $this->{$action}($request->getArg(), $request);
            if ($result instanceof Response) {
                return $result;
            }
            $response->setResult($result);
        } catch (\Throwable $throwable) {
            $this->onException($throwable, $response);
        } finally {
            $this->afterRequest($request, $response);
        }
        return $response;
    }

    protected function onRequest(Request $request): bool { return true; }
    protected function afterRequest(Request $request, Response $response): void {}
    protected function actionNotFound(Request $request, Response $response): Response
    {
        return $response->setStatus(Response::STATUS_ACTION_NOT_EXIST);
    }
    protected function onException(\Throwable $throwable, Response $response): void
    {
        $response->setStatus(Response::STATUS_SERVICE_ERROR)->setMsg($throwable->getMessage());
    }

    private function isActionAllowed(string $action): bool
    {
        if ($action === '' || str_starts_with($action, '__') || !method_exists($this, $action)) {
            return false;
        }
        $method = new \ReflectionMethod($this, $action);
        return $method->isPublic() && $method->getAttributes(RpcAction::class) !== [];
    }
}
