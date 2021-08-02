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
    /** @var callable|null */
    private $onSuccess;
    /** @var callable|null */
    private $onFail;
    /** @var mixed */
    private $clientArg;

    public function __construct(NodeManagerInterface $manager, ClientConfig $config)
    {
        $this->nodeManager = $manager;
        $this->config = $config;
    }

    public function addRequest(string $requestPath, ?int $serviceVersion = null): RequestContext
    {
        $req = new RequestContext();
        $req->setRequestPath($requestPath);
        $req->setServiceVersion($serviceVersion);
        $this->requestContext[] = $req;
        return $req;
    }

    /**
     * @param callable|null $onSuccess
     * @return $this
     */
    public function setOnSuccess(?callable $onSuccess): Client
    {
        $this->onSuccess = $onSuccess;
        return $this;
    }

    /**
     * @param callable|null $onFail
     * @return $this
     */
    public function setOnFail(?callable $onFail): Client
    {
        $this->onFail = $onFail;
        return $this;
    }

    public function exec(float $timeout = 3.0): int
    {
        $start = time();
        $channel = new Channel(256);
        /** @var RequestContext $requestContext */
        foreach ($this->requestContext as $requestContext) {
            Coroutine::create(function () use ($requestContext, $channel, $timeout) {
                $requestPath = $requestContext->getRequestPath();
                $requestPaths = explode('.', $requestPath);
                $service = array_shift($requestPaths);
                $module = array_shift($requestPaths);
                $action = array_shift($requestPaths);
                $node = $requestContext->getServiceNode();
                if (!$node) {
                    $node = $this->nodeManager->getNode($service, $requestContext->getServiceVersion());
                }
                $res = new Response();
                if (empty($node)) {
                    $res->setStatus(Response::STATUS_NOT_AVAILABLE_NODE);
                } else {
                    $requestContext->setServiceNode($node);
                    $pack = new Request();
                    $pack->setService($service);
                    $pack->setModule($module);
                    $pack->setAction($action);
                    $pack->setArg($requestContext->getArg());
                    $pack->setRequestUUID($requestContext->getRequestUUID());
                    $pack->setClientArg($this->clientArg);
                    $client = new TcpClient($this->config->getMaxPackageSize(), $timeout);
                    if (!$client->connect($node)) {
                        $res->setStatus(Response::STATUS_CONNECT_TIMEOUT);
                    } else {
                        $client->sendRequest($pack);
                        $res = $client->recv();
                    }
                }
                $channel->push([
                    'context' => $requestContext,
                    'response' => $res
                ]);
            });
        }
        $all = count($this->requestContext);
        $left = $timeout;
        while ((time() < $start + $timeout) && $all > 0) {
            $t = microtime(true);
            $ret = $channel->pop($left + 0.01);
            if ($ret) {
                $all--;
                $this->execCallback($ret['response'], $ret['context']);
            }
            $left = $left - (microtime(true) - $t);
            if ($left < 0 || $all <= 0) {
                break;
            }
        }
        return $all;
    }

    /**
     * @return mixed
     */
    public function getClientArg()
    {
        return $this->clientArg;
    }

    /**
     * @param mixed $clientArg
     */
    public function setClientArg($clientArg): void
    {
        $this->clientArg = $clientArg;
    }


    private function execCallback(Response $response, RequestContext $context)
    {
        //失败状态监测
        $failStatus = [
            Response::STATUS_CONNECT_TIMEOUT,
            Response::STATUS_SERVER_TIMEOUT,
            Response::STATUS_SERVICE_SHUTDOWN,
            Response::STATUS_SERVICE_ERROR
        ];
        if (in_array($response->getStatus(), $failStatus)) {
            if ($context->getServiceNode()) {
                $this->nodeManager->failDown($context->getServiceNode());
            }
        }
        $call = null;
        $globalCall = null;
        $clientCall = null;
        if ($response->getStatus() === Response::STATUS_OK) {
            $globalCall = $this->config->getOnGlobalSuccess();
            $call = $context->getOnSuccess();
            $clientCall = $this->onSuccess;
        } else {
            $globalCall = $this->config->getOnGlobalFail();
            $call = $context->getOnFail();
            $clientCall = $this->onFail;
        }

        if (is_callable($globalCall)) {
            call_user_func($globalCall, $response, $context);
        }

        if (is_callable($clientCall)) {
            call_user_func($clientCall, $response, $context);
        }

        if (is_callable($call)) {
            call_user_func($call, $response, $context);
        }
    }
}