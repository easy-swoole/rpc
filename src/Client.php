<?php


namespace EasySwoole\Rpc;


use EasySwoole\Rpc\Client\RequestContext;
use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Network\TcpClient;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use EasySwoole\Rpc\Config\Client as ClientConfig;

class Client
{
    private $nodeManager;
    private $requestContext = [];
    private $config;

    function __construct(NodeManagerInterface $manager, ClientConfig $config)
    {
        $this->nodeManager = $manager;
        $this->config = $config;
    }

    function addRequest(string $requestPath,?int $serviceVersion = null):RequestContext
    {
        $req = new RequestContext();
        $req->setRequestPath($requestPath);
        $req->setServiceVersion($serviceVersion);
        $this->requestContext[] = $req;
        return $req;
    }

    function exec(float $timeout = 3.0)
    {
        $start = time();
        $channel = new Channel(256);
        /** @var RequestContext $requestContext */
        foreach ($this->requestContext as $requestContext)
        {
            Coroutine::create(function ()use($requestContext,$channel,$timeout){
                $requestPath = $requestContext->getRequestPath();
                $requestPaths = explode('.',$requestPath);
                $service = array_shift($requestPaths);
                $module = array_shift($requestPaths);
                $action = array_shift($requestPaths);
                $node = $requestContext->getServiceNode();
                if(!$node){
                    $node = $this->nodeManager->getNode($service,$requestContext->getServiceVersion());
                }
                $res = new Response();
                if(empty($node)){
                    $res->setStatus(Response::STATUS_SERVICE_NODE_EMPTY);
                }else{
                    $requestContext->setServiceNode($node);
                    $pack = new Request();
                    $pack->setService($service);
                    $pack->setModule($module);
                    $pack->setAction($action);
                    $pack->setArg($requestContext->getArg());
                    $client = new TcpClient($this->config->getMaxPackageSize(),$timeout);
                    if(!$client->connect($node)){
                        $res->setStatus(Response::STATUS_CONNECT_TIMEOUT);
                    }else{
                        $client->sendRequest($pack);
                        $res = $client->recv();
                    }
                }
                $channel->push([
                    'context'=>$requestContext,
                    'response'=>$res
                ]);
            });
        }
        $all = count($this->requestContext);
        $left = $timeout;
        while ((time() < $start + $timeout) && $all > 0){
            $t = microtime(true);
            $ret = $channel->pop($left);
            if($ret){
                $all--;
                $this->execCallback($ret['response'],$ret['context']);
            }
            $left = $left - (microtime(true) - $t);
            if($left <0 || $all <=0){
                break;
            }
        }
        return $all;
    }

    private function execCallback(Response $response,RequestContext $context)
    {
        //失败状态监测
        $failStatus = [
            Response::STATUS_CONNECT_TIMEOUT,
            Response::STATUS_SERVER_TIMEOUT,
            Response::STATUS_SERVICE_SHUTDOWN,
            Response::STATUS_SERVICE_ERROR
        ];
        if(in_array($response->getStatus(),$failStatus)){
            if($context->getServiceNode()){
                $this->nodeManager->failDown($context->getServiceNode());
            }
        }
        $call = null;
        $globalCall = null;
        if($response->getStatus() === Response::STATUS_OK){
            $globalCall = $this->config->getOnGlobalSuccess();
            $call = $context->getOnSuccess();
        }else{
            $globalCall = $this->config->getOnGlobalFail();
            $call = $context->getOnFail();
        }

        if(is_callable($globalCall)){
            call_user_func($globalCall,$response,$context);
        }

        if(is_callable($call)){
            call_user_func($call,$response,$context);
        }
    }
}