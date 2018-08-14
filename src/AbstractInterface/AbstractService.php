<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/27
 * Time: 下午10:32
 */

namespace EasySwoole\Rpc\AbstractInterface;


use EasySwoole\Rpc\Bean\Caller;
use EasySwoole\Rpc\Bean\Response;
use EasySwoole\Trace\Trigger;

abstract class AbstractService
{
    private $caller;
    private $response;
    private $trigger;

    final function __construct(Caller $caller,Response $response,Trigger $trigger)
    {
        $this->caller = $caller;
        $this->response = $response;
        $this->trigger = $trigger;
        $this->__hook();
    }

    /**
     * @return Caller
     */
    protected function getCaller(): Caller
    {
        return $this->caller;
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    protected function onCall():bool
    {
        return true;
    }

    protected function onException(\Throwable $throwable)
    {
        $this->getResponse()->setStatus(Response::STATUS_SERVICE_ERROR);
        $this->getResponse()->setMessage($throwable->getMessage());
        $this->trigger->throwable($throwable);
    }

    protected function actionNotFound(?string $action)
    {
        $this->getResponse()->setStatus(Response::STATUS_SERVICE_ERROR);
        $this->getResponse()->setMessage("Service action : {$action} not found");
    }

    protected function afterAction(?string $actionName)
    {

    }

    private function __hook()
    {
        $actionName = $this->getCaller()->getAction();
        try{
            if($this->onCall() !== false){
                if(method_exists($this,$actionName)){
                    //先设置默认OK
                    $this->getResponse()->setStatus(Response::STATUS_SERVICE_OK);
                    $this->$actionName();
                }else{
                    $this->actionNotFound($actionName);
                }
            }
        }catch (\Throwable $throwable){
            //行为中的异常才触发
            $this->onException($throwable);
        }finally{
            try{
                $this->afterAction($actionName);
            }catch (\Throwable $throwable){
               $this->trigger->throwable($throwable);
            }
        }
    }
}