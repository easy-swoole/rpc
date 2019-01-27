<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/7
 * Time: 8:24 PM
 */

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use EasySwoole\Rpc\Task\Service;
use EasySwoole\Rpc\Task\TaskObject;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Client as SwooleClient;

class Client
{
    private $config;
    private $taskList = [];
    private $nodeManager;

    function __construct(Config $config,NodeManagerInterface $nodeManager)
    {
        $this->config = $config;
        $this->nodeManager = $nodeManager;
    }

    function selectService(string $serviceName,string $version = null):Service
    {
        $key = md5($serviceName);
        if(isset($this->taskList[$key])){
            return $this->taskList[$key];
        }
        $item = new Service($serviceName,$version);
        $this->taskList[$key] = $item;
        return $item;
    }

    function exec(float $maxTime = 5.0):array
    {
        if(empty($this->taskList)){
            return [];
        }
        $successTaskId = [];
        $channel = new Channel(512);
        $channelNum = 0;
        /** @var  $serviceTaskList Service */
        foreach ($this->taskList as $serviceTaskList){
            $taskList = $serviceTaskList->getTaskList();
            //find nodes to Call
            $serviceNode = $this->nodeManager->getServiceNode($serviceTaskList->getServiceName(),$serviceTaskList->getVersion());
            if(!$serviceNode){
                $response = new Response();
                $response->setStatus($response::STATUS_NODES_EMPTY);
                /** @var  $task TaskObject */
                foreach ($taskList as $task){
                    $this->hookCallBack(clone $response,$task);
                }
            }else{
                /** @var  $task TaskObject */
                foreach ($taskList as $task){
                    $task->setExecNode($serviceNode);
                    $channelNum++;
                    //create swoole client and connect to serviceNode
                    go(function ()use($task,$channel){
                        $client = new SwooleClient(SWOOLE_TCP);
                        $client->set(Config::$PACKAGE_SETTING);
                        if(!$client->connect($task->getExecNode()->getServiceIp(),$task->getExecNode()->getServicePort(),$task->getTimeout())){
                            $response = new Response();
                            $response->setNodeId($task->getExecNode()->getNodeId());
                            $response->setStatus($response::STATUS_CONNECT_TIMEOUT);
                            $channel->push([
                                'response'=>$response,
                                'taskObject'=>$task
                            ]);
                        }else{
                            if($this->config->getSerializeType() == Config::SERIALIZE_TYPE_RAW){
                                $data = serialize($task);
                            }else{
                                $data = $task->__toString();
                            }
                            $data = ProtocolPackage::pack($data);
                            $client->send($data);
                            $response = $client->recv($task->getTimeout());
                            if(empty($response)){
                                $response = new Response();
                                $response->setStatus($response::STATUS_SERVER_TIMEOUT);
                                $response->setNodeId($task->getExecNode()->getNodeId());
                            }else{
                                $response = ProtocolPackage::unpack($response);
                                if($this->config->getSerializeType() == Config::SERIALIZE_TYPE_RAW){
                                    $response = unserialize($response);
                                }else{
                                    $response = json_decode($response,true);
                                }
                            }
                            $channel->push([
                                'response'=>$response,
                                'taskObject'=>$task
                            ]);
                        }
                    });
                }
            }
        }
        $start = round(microtime(true),4);
        while ($maxTime > 0){
            $ret = $channel->pop($maxTime);
            if(is_array($ret)){
                /** @var TaskObject $task */
                $task = $ret['taskObject'];
                /** @var Response $response */
                $response = $ret['response'];
                $successTaskId[] = $task->getTaskId();
                //run it at another go
                go(function ()use($task,$response){
                    $this->hookCallBack($response,$task);
                });
            }
            if(count($successTaskId) == $channelNum){
                $maxTime = -1;
            }else{
                //leftMaxTime = maxTime - current loop spend time
                $maxTime = $maxTime - (round(microtime(true),4) - $start);
                $start = round(microtime(true),4);
            }
        }
        return $successTaskId;
    }

    protected function hookCallBack(Response $response,TaskObject $taskObject)
    {
        if($response->getStatus() == $response::STATUS_OK){
            $call = $taskObject->getOnSuccess();
        }else{
            $call = $taskObject->getOnFail();
        }
        if(is_callable($call)){
            call_user_func($call,$response,$taskObject);
        }
    }

}