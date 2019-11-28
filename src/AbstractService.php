<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\TableManager;
use Swoole\Coroutine;

abstract class AbstractService
{
    private $allowMethods = [];
    private $defaultProperties = [];
    private $requests = [];
    private $responses = [];
    private $sockets = [];
    private $actions = [];


    /*
     * 禁止重写该方法，防止在构造函数中抛出异常
     */
    final public function __construct()
    {
        //支持在子类控制器中以private，protected来修饰某个方法不可见
        $forbidList = [
            '__hook', '__destruct',
            '__clone', '__construct', '__call',
            '__callStatic', '__get', '__set',
            '__isset', '__unset', '__sleep',
            '__wakeup', '__toString', '__invoke',
            '__set_state', '__clone', '__debugInfo',
            'onRequest', 'serviceName', 'version', 'actionList'
        ];

        $ref = new \ReflectionClass(static::class);
        $public = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($public as $item) {
            if ((!in_array($item->getName(), $forbidList)) && (!$item->isStatic())) {
                $this->allowMethods[] = $item->getName();
            }
        }
        //获取，生成属性默认值
        $properties = $ref->getProperties();
        foreach ($properties as $property) {
            //不重置静态变量与保护私有变量
            if ($property->isPublic() && !$property->isStatic()) {
                $name = $property->getName();
                $this->defaultProperties[$name] = $this->{$name};
            }
        }

    }

    protected function onRequest(?string $action): ?bool
    {
        return true;
    }

    protected function afterAction(?string $action)
    {

    }

    abstract public function serviceName(): string;

    protected function onException(\Throwable $throwable)
    {
        throw $throwable;
    }

    protected function request(): Request
    {
        return $this->requests[Coroutine::getCid()];
    }

    protected function response(): Response
    {
        return $this->responses[Coroutine::getCid()];
    }

    protected function socket(): Coroutine\Socket
    {
        return $this->sockets[Coroutine::getCid()];
    }

    protected function action(): ?string
    {
        return $this->actions[Coroutine::getCid()];
    }

    protected function actionNotFound(?string $action)
    {
        $this->response()->setStatus(Response::STATUS_SERVICE_ACTION_NOT_FOUND);
    }

    public function version(): string
    {
        return '1.0';
    }

    public function actionList(): array
    {
        return $this->allowMethods;
    }

    public function __hook(Request $request, Response $response, Coroutine\Socket $client)
    {
        $this->requests[Coroutine::getCid()] = $request;
        $this->responses[Coroutine::getCid()] = $response;
        $this->sockets[Coroutine::getCid()] = $client;
        $this->actions[Coroutine::getCid()] = $request->getAction();
        $actionName = $this->action();
        try {
            if ($this->onRequest($this->action()) !== false) {
                if (in_array($actionName, $this->allowMethods)) {
                    $actionName = $this->action();
                    $this->$actionName();
                } else {
                    $this->actionNotFound($this->action());
                }
            }
        } catch (\Throwable $throwable) {
            //若没有重构onException，直接抛出给上层
            $this->onException($throwable);
        } finally {
            try {
                $this->afterAction($this->action());
            } catch (\Throwable $throwable) {
                $this->onException($throwable);
            } finally {
                try {
                    $this->gc();
                } catch (\Throwable $throwable) {
                    $this->onException($throwable);
                }
            }
            unset($this->requests[Coroutine::getCid()]);
            unset($this->responses[Coroutine::getCid()]);
            unset($this->actions[Coroutine::getCid()]);
            unset($this->sockets[Coroutine::getCid()]);
        }
        if ($response->getStatus() === Response::STATUS_OK) {
            TableManager::getInstance()->get($this->serviceName())->incr($actionName, 'success');
        } else {
            TableManager::getInstance()->get($this->serviceName())->incr($actionName, 'fail');
        }
    }

    protected function gc()
    {
        //恢复默认值
        foreach ($this->defaultProperties as $property => $value) {
            $this->{$property} = $value;
        }
    }
}