<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Attribute\RpcAction;
use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;

abstract class AbstractServiceModule
{
    abstract public function moduleName(): string;

    final public function __dispatch(Request $request, Response $response): Response
    {
        try {
            if (!$this->onRequest($request)) {
                return $response
                    ->setStatus(ResponseStatus::SERVICE_SHUTDOWN)
                    ->setNodeHealthStatus(NodeHealthStatus::OFFLINE);
            }
            $action = $request->getAction();
            // 只有显式标记 #[RpcAction] 的 public 方法才能作为远程入口。
            if (!$this->isActionAllowed($action)) {
                return $this->actionNotFound($request, $response);
            }
            $result = $this->{$action}($request->getArg(), $request);
            if ($result instanceof Response) {
                if ($result->getRequestUUID() !== $request->getRequestUUID()) {
                    throw new RpcException('Module response request UUID must match the current request UUID.');
                }
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
        return $response->setStatus(ResponseStatus::ACTION_NOT_EXIST);
    }
    protected function onException(\Throwable $throwable, Response $response): void
    {
        $response
            ->setStatus(ResponseStatus::SERVICE_ERROR)
            ->setNodeHealthStatus(NodeHealthStatus::SUSPECT)
            ->setMsg('Server error.');
        try {
            trigger_error(sprintf(
                'Unhandled %s: %s in %s:%d',
                $throwable::class,
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
            ), E_USER_WARNING);
        } catch (\Throwable) {
            // Error reporting must never alter the RPC response lifecycle.
        }
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
