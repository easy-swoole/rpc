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

    function __construct(Config $config, NodeManagerInterface $nodeManager)
    {
        $this->config = $config;
        $this->nodeManager = $nodeManager;
    }

    /**
     * 设置查询的服务
     * @param string $serviceName
     * @param string|null $version
     * @return Service
     */
    function selectService(string $serviceName, string $version = null): Service
    {
        $key = md5($serviceName);
        if (isset($this->taskList[$key])) {
            return $this->taskList[$key];
        }
        $item = new Service($serviceName, $version);
        $this->taskList[$key] = $item;
        return $item;
    }

    /**
     * 执行
     * @param float $maxTime
     * @return array
     */
    function exec(float $maxTime = 5.0): array
    {
        if (empty($this->taskList)) {
            return [];
        }
        $successTaskId = [];
        $channel = new Channel(512);
        $channelNum = 0;
        /** @var  $serviceTaskList Service */
        foreach ($this->taskList as $serviceTaskList) {
            $taskList = $serviceTaskList->getTaskList();//服务下的所有任务列表
            //find nodes to Call 先去节点管理器搜索
            $serviceNode = $this->nodeManager->getServiceNode($serviceTaskList->getServiceName(), $serviceTaskList->getVersion());
            if (!$serviceNode) {
                $response = new Response();
                $response->setStatus($response::STATUS_NODES_EMPTY);
                /** @var  $task TaskObject */
                foreach ($taskList as $task) {
                    $this->hookCallBack(clone $response, $task);
                }
            } else {
                /** @var  $task TaskObject */
                foreach ($taskList as $task) {
                    $task->setExecNode($serviceNode);//设置执行的节点
                    $channelNum++;
                    //create swoole client and connect to serviceNode
                    go(function () use ($task, $channel) {
                        $client = new SwooleClient(SWOOLE_TCP);
                        $client->set($this->config->getPackageSetting());
                        if (!$client->connect($task->getExecNode()->getServiceIp(), $task->getExecNode()->getServicePort(), $task->getTimeout())) {
                            $response = new Response();
                            $response->setNodeId($task->getExecNode()->getNodeId());
                            $response->setStatus($response::STATUS_CONNECT_TIMEOUT);//调用超时也压入通道
                            $channel->push([
                                'response' => $response,
                                'taskObject' => $task
                            ]);
                        } else {
                            $data = $task->toArray(['arg', 'action']);
                            //使用何种方式解数据
                            if ($this->config->getSerializeType() == Config::SERIALIZE_TYPE_RAW) {
                                $data = serialize($data);
                            } else {
                                $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                            $data = Request::pack($data);//打包
                            $client->send($data);
                            $response = $client->recv($task->getTimeout());
                            if (empty($response)) {//没有数据返回为服务超时
                                $response = new Response();
                                $response->setStatus($response::STATUS_SERVER_TIMEOUT);
                                $response->setNodeId($task->getExecNode()->getNodeId());
                            } else {
                                $response = Request::unpack($response);
                                if ($this->config->getSerializeType() == Config::SERIALIZE_TYPE_RAW) {
                                    $response = unserialize($response);
                                } else {
                                    $response = json_decode($response, true);
                                    if (!is_array($response)) {
                                        $response = [];
                                    }
                                    $response = new Response($response);
                                }
                            }
                            $channel->push([
                                'response' => $response,
                                'taskObject' => $task
                            ]);
                        }
                    });
                }
            }
        }
        $start = round(microtime(true), 4);
        while ($maxTime > 0) {
            $ret = $channel->pop($maxTime);
            if (is_array($ret)) {
                /** @var TaskObject $task */
                $task = $ret['taskObject'];
                /** @var Response $response */
                $response = $ret['response'];
                $successTaskId[] = $task->getTaskId();
                //run it at another go
                go(function () use ($task, $response) {
                    $this->hookCallBack($response, $task);
                });
            }
            if (count($successTaskId) == $channelNum) {
                $maxTime = -1;//结束循环
            } else {
                //leftMaxTime = maxTime - current loop spend time
                $maxTime = $maxTime - (round(microtime(true), 4) - $start);
                $start = round(microtime(true), 4);
            }
        }
        return $successTaskId;
    }

    protected function hookCallBack(Response $response, TaskObject $taskObject)
    {
        if ($response->getStatus() == $response::STATUS_OK) {
            $call = $taskObject->getOnSuccess();
        } else {
            $call = $taskObject->getOnFail();
        }
        //回调setOnSuccess 或者 setOnFail
        if (is_callable($call)) {
            call_user_func($call, $response, $taskObject);
        }
    }

}