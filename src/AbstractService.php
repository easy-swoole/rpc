<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\TableManager;

abstract class AbstractService
{
    private $allowMethods = [];
    private $request;
    private $client;
    private $response;
    private $action;
    /*
     * 禁止重写该方法，防止在构造函数中抛出异常
     */
    final public function __construct()
    {
        //支持在子类控制器中以private，protected来修饰某个方法不可见
        $list = [];
        $ref = new \ReflectionClass(static::class);
        $public = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($public as $item) {
            array_push($list, $item->getName());
        }
        $this->allowMethods = array_diff($list,
            [
                '__hook', '__destruct',
                '__clone', '__construct', '__call',
                '__callStatic', '__get', '__set',
                '__isset', '__unset', '__sleep',
                '__wakeup', '__toString', '__invoke',
                '__set_state', '__clone', '__debugInfo',
                'serviceName','version','onTick','actionList'
            ]
        );
    }

    protected function onRequest(?string $action):?bool
    {
        return true;
    }

    protected function afterAction(?string $action)
    {

    }

    abstract public function serviceName():string ;
    /*
     * 每秒会执行一次，请自己实现间隔需求
     */
    public function onTick(Config $config)
    {

    }

    protected function onException(\Throwable $throwable)
    {
        throw $throwable;
    }

    protected function request():Request
    {
        return $this->request;
    }

    protected function response():Response
    {
        return $this->response;
    }

    protected function client():SocketClient
    {
        return $this->client;
    }

    protected function action(): ?string
    {
        return $this->action;
    }

    protected function actionNotFound(?string $action)
    {
        $this->response()->setStatus(Response::STATUS_SERVICE_ACTION_NOT_FOUND);
    }

    public function version():string
    {
        return '1.0';
    }

    public function actionList():array
    {
        return $this->allowMethods;
    }

    public function __hook(Request $request, Response $response, SocketClient $client)
    {
        $this->request = $request;
        $this->response = $response;
        $this->client = $client;
        $this->action = $request->getAction();
        try {
            if ($this->onRequest($this->action()) !== false) {
                if (in_array($this->action, $this->allowMethods)) {
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
            }
        }
        if($response->getStatus() === Response::STATUS_OK){
            TableManager::getInstance()->get($this->serviceName())->incr($this->action(),'success');
        }else{
            TableManager::getInstance()->get($this->serviceName())->incr($this->action(),'fail');
        }
    }

}