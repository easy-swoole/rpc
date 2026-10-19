<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Exception\RpcException;

use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;

abstract class AbstractService
{
    /** @var array<string, AbstractServiceModule> */
    private array $modules = [];
    private bool $frozen = false;

    abstract public function serviceName(): string;

    public function serviceWeight(): int
    {
        return 10;
    }

    public function addModule(AbstractServiceModule $module): static
    {
        if ($this->frozen) {
            throw new RpcException(sprintf('Service "%s" is frozen and cannot add modules.', $this->serviceName()));
        }
        $moduleName = $module->moduleName();
        if ($moduleName === '') {
            throw new RpcException('Module name cannot be empty.');
        }
        if (isset($this->modules[$moduleName])) {
            throw new RpcException(sprintf('Module "%s" is already registered.', $moduleName));
        }
        $module->__prepareActions();
        $this->modules[$moduleName] = $module;
        return $this;
    }

    /** @internal Called by ServiceRegistry after successful registration. */
    final public function freeze(): void
    {
        // ServiceRegistry 注册完成后冻结，避免 fork 后服务定义在不同进程中发生分叉。
        $this->frozen = true;
    }

    public function __dispatch(Request $request, Response $response): void
    {
        try {
            if (!$this->onRequest($request)) {
                $response
                    ->setStatus(ResponseStatus::SERVICE_SHUTDOWN)
                    ->setNodeHealthStatus(NodeHealthStatus::OFFLINE);
                return;
            }
            $module = $this->modules[$request->getModule()] ?? null;
            if ($module === null) {
                // Module 不存在时直接交给 Service 处理；Action 权限属于 Module 的职责。
                $this->moduleNotFound($request, $response);
                return;
            }
            $module->__dispatch($request, $response);
        } catch (\Throwable $throwable) {
            $this->onException($throwable, $response);
        } finally {
            $this->afterRequest($request, $response);
        }
    }

    protected function onRequest(Request $request): bool { return true; }
    protected function afterRequest(Request $request, Response $response): void {}
    protected function moduleNotFound(Request $request, Response $response): void
    {
        $response->setStatus(ResponseStatus::MODULE_NOT_EXIST);
    }
    protected function onException(\Throwable $throwable, Response $response): void
    {
        // 默认不吞掉异常；业务只有在明确覆写此方法后才表示已经处理。
        throw $throwable;
    }
}
