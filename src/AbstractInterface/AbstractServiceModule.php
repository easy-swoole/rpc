<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Attribute\RpcAction;
use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseBuilder;
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
                throw new RpcException(sprintf('RPC action "%s::%s" cannot declare Response as a return type or return a ResponseBuilder.', $reflection->getName(), $name));
            }
            $this->allowedActions[$name] = true;
        }
        $this->actionsPrepared = true;
    }

    final public function __dispatch(Request $request, ResponseBuilder $response): void
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
            if (!isset($this->allowedActions[$action])) {
                $this->actionNotFound($request, $response);
                return;
            }
            // Action 通过返回值提供 result，通过第三个参数设置响应元数据。
            $result = $this->{$action}($request->getArg(), $request, $response);
            if ($result instanceof Response || $result instanceof ResponseBuilder) {
                throw new RpcException('RPC actions must return business data instead of a Response object or ResponseBuilder.');
            }
            $response->setResult($result);
        } catch (\Throwable $throwable) {
            $this->onException($throwable, $response);
        } finally {
            $this->afterRequest($request, $response);
        }
    }

    protected function onRequest(Request $request): bool { return true; }
    protected function afterRequest(Request $request, ResponseBuilder $response): void {}
    protected function actionNotFound(Request $request, ResponseBuilder $response): void
    {
        $response->setStatus(ResponseStatus::ACTION_NOT_EXIST);
    }
    protected function onException(\Throwable $throwable, ResponseBuilder $response): void
    {
        // 默认继续向 Service 和 Server 冒泡，避免开发阶段静默丢失异常。
        throw $throwable;
    }

    private function typeContainsResponse(?\ReflectionType $type): bool
    {
        if ($type instanceof \ReflectionNamedType) {
            return in_array($type->getName(), [Response::class, ResponseBuilder::class], true);
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
