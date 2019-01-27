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
        $taskQueue = [];
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
                    $taskQueue[] = $task;
                }
            }
            //create swoole client and connect to serviceNode
        }
        $successTaskId = [];
        return $successTaskId;
    }

    protected function hookCallBack(Response $response,TaskObject $taskObject)
    {

    }

}