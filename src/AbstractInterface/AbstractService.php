<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;

abstract class AbstractService
{
    /** @var array<string, AbstractServiceModule> */
    private array $modules = [];

    abstract public function serviceName(): string;

    public function serviceWeight(): int
    {
        return 10;
    }

    public function addModule(AbstractServiceModule $module): static
    {
        $this->modules[$module->moduleName()] = $module;
        return $this;
    }

    public function __dispatch(Request $request, Response $response): Response
    {
        try {
            if (!$this->onRequest($request)) {
                return $response->setStatus(Response::STATUS_SERVICE_SHUTDOWN);
            }
            $module = $this->modules[$request->getModule()] ?? null;
            if ($module === null) {
                return $this->moduleNotFound($request, $response);
            }
            return $module->__dispatch($request, $response);
        } catch (\Throwable $throwable) {
            $this->onException($throwable, $response);
        } finally {
            $this->afterRequest($request, $response);
        }
        return $response;
    }

    protected function onRequest(Request $request): bool { return true; }
    protected function afterRequest(Request $request, Response $response): void {}
    protected function moduleNotFound(Request $request, Response $response): Response
    {
        return $response->setStatus(Response::STATUS_MODULE_NOT_EXIST);
    }
    protected function onException(\Throwable $throwable, Response $response): void
    {
        $response->setStatus(Response::STATUS_SERVICE_ERROR)->setMsg($throwable->getMessage());
    }
}
