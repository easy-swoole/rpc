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

    public function addModule(AbstractServiceModule $module): self
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
            if ($request->getModule() !== null) {
                $module = $this->modules[$request->getModule()] ?? null;
                if ($module === null) {
                    return $response->setStatus(Response::STATUS_MODULE_NOT_EXIST);
                }
                return $module->__dispatch($request, $response);
            }
            $action = $request->getAction();
            if ($action === null || !$this->isActionAllowed($action)) {
                return $response->setStatus(Response::STATUS_ACTION_NOT_EXIST);
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
        return $method->isPublic() && $method->getDeclaringClass()->getName() !== self::class;
    }

}
