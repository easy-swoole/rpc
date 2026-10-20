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
    /** @var array<string, true> */
    private array $allowedActions = [];
    private bool $actionsPrepared = false;

    abstract public function moduleName(): string;

    /** @internal Called when the module is registered with a Service. */
    final public function __prepareActions(): void
    {
        if ($this->actionsPrepared) {
            return;
        }
        // Action 元数据在服务装配阶段只反射一次，请求阶段只进行数组查找。
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getMethods() as $method) {
            if ($method->getAttributes(RpcAction::class) === []) {
                continue;
            }
            $name = $method->getName();
            if (!$method->isPublic()) {
                throw new RpcException(sprintf('RPC action "%s::%s" must be public.', $reflection->getName(), $name));
            }
            if ($method->isStatic()) {
                throw new RpcException(sprintf('RPC action "%s::%s" cannot be static.', $reflection->getName(), $name));
            }
            if (str_starts_with($name, '__')) {
                throw new RpcException(sprintf('RPC action "%s::%s" cannot use a reserved name.', $reflection->getName(), $name));
            }
            if ($method->getNumberOfRequiredParameters() > 3) {
                throw new RpcException(sprintf('RPC action "%s::%s" cannot require more than 3 parameters.', $reflection->getName(), $name));
            }
            if ($this->typeContainsResponse($method->getReturnType())) {
                throw new RpcException(sprintf('RPC action "%s::%s" cannot declare Response as a return type.', $reflection->getName(), $name));
            }
            $this->allowedActions[$name] = true;
        }
        $this->actionsPrepared = true;
    }

    final public function __dispatch(Request $request, Response $response): void
    {
        // 正常情况下已由 addModule() 预热；保留惰性兜底以支持直接调度测试等场景。
        $this->__prepareActions();
        try {
            if (!$this->onRequest($request)) {
                $response
                    ->setStatus(ResponseStatus::SERVICE_SHUTDOWN)
                    ->setNodeHealthStatus(NodeHealthStatus::OFFLINE);
                return;
            }
            $action = $request->getAction();
            // 只有显式标记 #[RpcAction] 的 public 方法才能作为远程入口。
            if (!$this->isActionAllowed($action)) {
                $this->actionNotFound($request, $response);
                return;
            }
            // Response 由框架创建并作为第三个参数传入；Action 返回值只表示业务 result。
            $result = $this->{$action}($request->getArg(), $request, $response);
            if ($result instanceof Response) {
                throw new RpcException('RPC actions must return business data instead of a Response object.');
            }
            $response->setResult($result);
        } catch (\Throwable $throwable) {
            $this->onException($throwable, $response);
        } finally {
            $this->afterRequest($request, $response);
        }
    }

    protected function onRequest(Request $request): bool { return true; }
    protected function afterRequest(Request $request, Response $response): void {}
    protected function actionNotFound(Request $request, Response $response): void
    {
        $response->setStatus(ResponseStatus::ACTION_NOT_EXIST);
    }
    protected function onException(\Throwable $throwable, Response $response): void
    {
        // 默认继续向 Service 和 Server 冒泡，避免开发阶段静默丢失异常。
        throw $throwable;
    }

    private function isActionAllowed(string $action): bool
    {
        return isset($this->allowedActions[$action]);
    }

    private function typeContainsResponse(?\ReflectionType $type): bool
    {
        if ($type instanceof \ReflectionNamedType) {
            return !$type->isBuiltin() && $type->getName() === Response::class;
        }
        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $nestedType) {
                if ($this->typeContainsResponse($nestedType)) {
                    return true;
                }
            }
        }
        return false;
    }
}
