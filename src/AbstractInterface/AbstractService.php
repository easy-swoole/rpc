<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;

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
            throw new \LogicException(sprintf('Service "%s" is frozen and cannot add modules.', $this->serviceName()));
        }
        $moduleName = $module->moduleName();
        if ($moduleName === '') {
            throw new \InvalidArgumentException('Module name cannot be empty.');
        }
        if (isset($this->modules[$moduleName])) {
            throw new \LogicException(sprintf('Module "%s" is already registered.', $moduleName));
        }
        $this->modules[$moduleName] = $module;
        return $this;
    }

    final public function __freeze(): void
    {
        $this->frozen = true;
    }

    public function __dispatch(Request $request, Response $response): Response
    {
        try {
            if (!$this->onRequest($request)) {
                return $response->setStatus(ResponseStatus::SERVICE_SHUTDOWN);
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
        return $response->setStatus(ResponseStatus::MODULE_NOT_EXIST);
    }
    protected function onException(\Throwable $throwable, Response $response): void
    {
        $response->setStatus(ResponseStatus::SERVICE_ERROR)->setMsg('Server error.');
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
}
