<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/1
 * Time: 下午4:25
 */

namespace EasySwoole\Rpc\Client;


use EasySwoole\Component\Openssl;
use EasySwoole\Rpc\Bean\Caller;
use EasySwoole\Rpc\Bean\Response;
use EasySwoole\Rpc\Bean\ServiceNode;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Rpc;
use Swoole\Coroutine;

class Client
{
    private $config;
    private $tasks = [];
    private $taskClients = [];
    private $openssl = null;
    private $cid = null;
    private $onException;
    function __construct(Config $config)
    {
        $this->config = $config;
        if($config->getSecretKey()){
            $this->openssl = new Openssl($config->getSecretKey());
        }
    }

    function addCall(string $service,string $action,...$args):Task
    {
        $call = new Caller();
        $call->setService($service);
        $call->setAction($action);
        $call->setArgs($args);
        $task = new Task($call);
        $this->tasks[spl_object_hash($task)] = $task;
        return $task;
    }

    /**
     * @throws \Throwable
     */
    function exec(float $timeout = 0.5)
    {
        foreach ($this->tasks as $task){
            $this->buildConnect($task,$timeout);
        }
        $this->cid = Coroutine::getuid();
        //开启另外一个协程来做调度
        go(function ()use($timeout){
            $i = intval($timeout*1000);
            while ($this->cid != null && $i > 0){
                Coroutine::sleep(0.001);
                $i--;
            }
            if($this->cid != null){
                Coroutine::resume($this->cid);
                $this->cid = null;
            }
        });
        Coroutine::suspend($this->cid);
        $this->cid = null;
        //超时任务处理
        foreach ($this->tasks as $task){
            $this->taskCallBack($task,new Response([
                'status'=>Response::STATUS_SERVICE_TIMEOUT
            ]));
        }
    }

    /**
     * @throws \Throwable
     */
    private function buildConnect(Task $task,float $timeout)
    {
        $serviceNode = Rpc::getInstance()->getServiceNode($task->getCaller()->getService());
        if($serviceNode instanceof ServiceNode){
            $client = new \swoole_client(SWOOLE_SOCK_TCP,SWOOLE_SOCK_ASYNC);
            $client->set([
                'open_length_check' => true,
                'package_length_type'   => 'N',
                'package_length_offset' => 0,
                'package_body_offset'   => 4,
                'package_max_length'    => $this->config->getMaxPackage(),
            ]);
            $client->on("connect", function(\swoole_client $cli)use($task){
               $data = $task->getCaller()->__toString();
               if($this->openssl instanceof Openssl){
                   $data = $this->openssl->encrypt($data);
               }
               $cli->send(Rpc::dataPack($data));
            });
            $client->on('error',function (\swoole_client $client)use($task){
                $this->taskCallBack($task,new Response([
                    'status'=>Response::STATUS_CONNECT_FAIL
                ]));
            });

            $client->on("receive", function(\swoole_client $client, $data)use($task) {
                $data = Rpc::dataUnPack($data);
                if($this->openssl instanceof Openssl){
                    $data = $this->openssl->decrypt($data);
                }
                $json = json_decode($data,true);
                if(is_array($json)){
                    $this->taskCallBack($task,new Response($json));
                }else{
                    $this->taskCallBack($task,new Response([
                        'status'=>Response::STATUS_PACKAGE_ERROR
                    ]));
                }
            });

            $client->on('close',function (\swoole_client $client)use($task){

                /*
                 * 如果断开连接时，还存在任务，那么说明，该任务未执行receive回调
                 */
                if(isset($this->tasks[spl_object_hash($task)])){
                    $this->taskCallBack($task,new Response([
                        'status'=>Response::STATUS_PACKAGE_ERROR
                    ]));
                }

            });
            $client->connect($serviceNode->getIp(),$serviceNode->getPort(),$timeout);
            $this->taskClients[spl_object_hash($task)] = $client;
        }else{
            $this->taskCallBack($task,new Response([
                'status'=>Response::STATUS_SERVICE_NOT_FOUND
            ]));
        }
    }

    private function removeTask(Task $task)
    {
        $key = spl_object_hash($task);
        if(isset($this->tasks[$key])){
            unset($this->tasks[$key]);
        }
        if(isset($this->taskClients[$key])){
            $client = $this->taskClients[$key];
            if($client->isConnected()){
                $client->close();
            }
            unset($this->taskClients[$key]);
        }
    }

    /**
     * @param Task $task
     * @param Response $response
     * @throws \Throwable
     */
    private function taskCallBack(Task $task, Response $response)
    {
        if($response->getStatus() == Response::STATUS_SERVICE_OK){
            $handler = $task->getSuccess();
        }else{
            $handler = $task->getFail();
        }
        if(is_callable($handler)){
            try{
                call_user_func($handler,$response);
            }catch (\Throwable $throwable){
                if(is_callable($this->onException)){
                    call_user_func($this->onException,$task,$response);
                }else{
                    throw $throwable;
                }
            }
        }
        $this->removeTask($task);
        if(!empty($this->cid) && empty($this->tasks)){
            Coroutine::resume($this->cid);
        }
    }
}