<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/7
 * Time: 8:24 PM
 */

namespace EasySwoole\Rpc;

use EasySwoole\Component\Openssl;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Client as SwooleClient;

class Client
{
    private $config;
    private $taskList = [];
    private $nodeManager;
    private $openssl;
    function __construct(Config $config,NodeManager $nodeManager)
    {
        $this->config = $config;
        $this->nodeManager = $nodeManager;
        if(!empty($this->config->getAuthKey()) && $config->isEnableOpenssl()){
            $this->openssl = new Openssl($this->config->getAuthKey());
        }
    }

    function selectService(string $serviceName,string $version = null)
    {
        $task = new Task();
        $this->taskList[spl_object_hash($task)] = [
            'serviceName'=>$serviceName,
            'version'=>$version,
            'task'=>$task
        ];
        return $task;
    }

    /*
     * 全部任务时间
     */
    function call(float $maxWaitTime = 2.0)
    {
        $startTime = round(microtime(true),3);
        $channel = new Channel(count($this->taskList)+1);

        foreach ($this->taskList as $taskUid => $taskArray){
            $node = $this->nodeManager->getServiceNode($taskArray['serviceName'],$taskArray['version']);
            if($node instanceof ServiceNode){
                go(function ()use($channel,$node,$taskArray,$taskUid,$maxWaitTime){
                    $taskClient = new SwooleClient(SWOOLE_SOCK_TCP);
                    $taskClient->set($this->config->getProtocolSetting());
                    if($taskClient->connect($node->getServiceIp(),$node->getServicePort(),$taskArray['task']->__getTimeout())){
                        $package = new RequestPackage([
                            'action'=>$taskArray['task']->__getAction(),
                            'arg'=>$taskArray['task']->__getArg(),
                            'nodeId'=>$this->config->getNodeId()
                        ]);
                        $package->setPackageTime();
                        $package->generateSignature($this->config->getAuthKey());
                        $msg = (string)$package;
                        if($this->openssl){
                            $msg = $this->openssl->encrypt($msg);
                        }
                        $taskClient->send(Pack::pack($msg));
                        $this->taskList[$taskUid]['taskClient'] = $taskClient;
                        $this->taskList[$taskUid]['serviceNode'] = $node;
                        $time = $maxWaitTime > $taskArray['task']->__getTimeout() ? $taskArray['task']->__getTimeout() : $maxWaitTime;
                        $data = Pack::unpack($taskClient->recv($time));
                        if($this->openssl){
                            $data = $this->openssl->decrypt($data);
                        }
                        $raw = json_decode($data,true);
                        if(!is_array($raw)){
                            $raw = [];
                        }
                        $taskArray['response'] = new Response($raw);
                        $channel->push(['taskArray'=>$taskArray,'serviceNode'=>$node]);
                    }else{
                        $taskArray['response'] = new Response([
                            'status'=>Response::STATUS_CONNECT_TIMEOUT
                        ]);
                        $this->hookCallBack($taskArray['task'],$taskArray['response'],$node);

                    }
                });
            }else{
                $taskArray['response'] = new Response([
                    'status'=>Response::STATUS_NODES_EMPTY
                ]);
                $this->hookCallBack($taskArray['task'],$taskArray['response'],$node);
            }
        }
        //执行调度
        for ($i = 0;count($this->taskList) > 0;$i++){
            $taskArray = $channel->pop(0.001);
            if(!empty($taskArray)){
                $this->hookCallBack($taskArray["taskArray"]['task'],$taskArray["taskArray"]['response'],$taskArray['serviceNode']);
            }
            if(round(microtime(true),3) - $startTime > $maxWaitTime){
                break;
            }
        }
        foreach ($this->taskList as $taskUid => $taskArray){
            $this->taskList[$taskUid]['response'] = $taskArray['response'] = new Response([
                'status'=>Response::STATUS_CONNECT_TIMEOUT
            ]);
            $this->hookCallBack($taskArray['task'],$taskArray['response'],$taskArray['serviceNode']);
        }

    }

    private function hookCallBack(Task $task,Response $response ,?ServiceNode $serviceNode)
    {
        $hash = spl_object_hash($task);
        if(isset($this->taskList[$hash]['taskClient'])){
            $this->taskList[$hash]['taskClient']->close();
        }
        unset($this->taskList[$hash]);
        if($response->getStatus() == Response::STATUS_OK){
            $call = $task->__getOnSuccess();
        }else{
            $call = $task->__getOnFail();
        }
        if(is_callable($call)){
            call_user_func($call,$task,$response,$serviceNode);
        }
    }
}