<?php


namespace EasySwoole\Rpc;


use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class RpcClient
{
    protected $nodeManager;
    protected $callList = [];
    protected $clientConfig;

    public function __construct(NodeManagerInterface $manager,ClientConfig $clientConfig)
    {
        $this->nodeManager = $manager;
        $this->clientConfig = $clientConfig;
    }

    public function serverStatus(ServerNode $serverNode, float $timeout = 3.0): Response
    {
        $command = new Command();
        $command->setCommand(Command::SERVICE_STATUS);
        $client = new TcpClient($serverNode, $timeout);
        $client->sendCommand($command);
        return $client->recv();
    }

    public function addCall(string $service, string $action, $arg = null, $serviceVersion = null): ServiceCall
    {
        $item = new ServiceCall([
            'serviceName' => $service,
            'action' => $action,
            'arg' => $arg,
            'serviceVersion' => $serviceVersion
        ]);
        $this->callList[] = $item;
        return $item;
    }

    function exec(float $timeout = 3.0)
    {
        $list = [];
        /** @var ServiceCall $item */
        foreach ($this->callList as $item) {
            /*
             * 如果指定了节点
             */
            $serviceNode = null;
            if ($item->getServiceNode()) {
                $serviceNode = $item->getServiceNode();
            } else {
                $serviceNode = $this->nodeManager->getServiceNode($item->getServiceName(), $item->getServiceVersion());
            }
            if (!$serviceNode) {
                $response = new Response([
                    'status' => Response::STATUS_NODES_EMPTY
                ]);
                $this->callback($response, $item);
            } else {
                $item->setServiceNode($serviceNode);
                $command = new Command();
                $command->setCommand(Command::SERVICE_CALL);
                $command->setRequest(new Request($item->toArray()));
                $client = new TcpClient($serviceNode,$timeout,$this->clientConfig->getClientSettings());
                $client->sendCommand($command);
                $list[] = [
                    'client' => $client,
                    'call' => $item
                ];
            }
        }
        $channel = new Channel(128);
        foreach ($list as $item) {
            Coroutine::create(function () use ($item, $channel, $timeout) {
                $channel->push([
                    'response' => $item['client']->recv($timeout),
                    'call' => $item['call']
                ]);
            });
        }

        $this->callList = [];

        $left = $timeout;
        $leftHandler = count($list);
        while ($left > 0 && $leftHandler > 0) {
            $start = round(microtime(true), 3);
            $ret = $channel->pop($left);
            $left = $left - (round(microtime(true), 3) - $start);
            $leftHandler--;
            if (is_array($ret)) {
                $this->callback($ret['response'], $ret['call']);
            }
        }
    }

    private function callback(Response $response, ServiceCall $serviceCall)
    {
        if ($response->getStatus() == $response::STATUS_OK) {
            $globalCall = $this->clientConfig->getOnGlobalSuccess();
            $call = $serviceCall->getOnSuccess();
        } else {
            $globalCall = $this->clientConfig->getOnGlobalFail();
            $call = $serviceCall->getOnFail();
        }
        //全局回调出错不应该影响业务逻辑回调
        try{
            if (is_callable($globalCall)) {
                call_user_func($globalCall, $response, $serviceCall);
            }
        }catch (\Throwable $throwable){
            throw $throwable;
        } finally {
            if (is_callable($call)) {
                call_user_func($call, $response, $serviceCall);
            }
        }
    }
}