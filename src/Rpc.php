<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/27
 * Time: 下午5:51
 */

namespace EasySwoole\Rpc;


use EasySwoole\Rpc\AutoFind\Process;
use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use Swoole\Server\Port;

class Rpc
{
    private $config;
    private $client;
    private $actionList = [];
    private $onRequest;
    private $afterRequest;
    private $onException;
    private $actionNotFound;

    function __construct(Config $config)
    {
        $this->config = $config;
    }

    function onRequest(callable $call): Rpc
    {
        $this->onRequest = $call;
        return $this;
    }

    function afterRequest(callable $call): Rpc
    {
        $this->afterRequest = $call;
        return $this;
    }

    function onException(callable $call): Rpc
    {
        $this->onException = $call;
        return $this;
    }

    function actionNotFound(callable $call): Rpc
    {
        $this->actionNotFound = $call;
        return $this;
    }

    public function registerAction(string $actionName, callable $callback): Rpc
    {
        $this->actionList[$actionName] = $callback;
        return $this;
    }

    /**
     * 实例自定义进程 实现监听和广播
     * @param string $processName
     * @return Process
     */
    public function autoFindProcess($processName = 'EASYSWOOLE_RPC_AUTO_FIND_PROCESS'): Process
    {
        $p = new Process($processName, $this->config, false, 2, true);
        return $p;
    }

    /*
     * 每个进程中的client互相隔离
     */
    function client(): Client
    {
        if (!$this->client) {
            $this->client = new Client($this->config, $this->config->getNodeManager());
        }
        return $this->client;
    }

    function nodeManager(): NodeManagerInterface
    {
        return $this->config->getNodeManager();
    }

    /**
     * @param $server \swoole_server |Port
     */
    public function attachToServer($server)
    {
        $server->set($this->config->getPackageSetting());
        $server->on('receive', function ($server, int $fd, int $reactor_id, string $data) {
            $this->onRpcReceive($server, $fd, $reactor_id, $data);
        });
        $this->config->setServicePort($server->port);
    }

    /**
     * 实现rpc接收数据处理
     * @param $server \swoole_server
     * @param int $fd
     * @param int $reactor_id
     * @param string $data
     */
    private function onRpcReceive($server, int $fd, int $reactor_id, string $data): void
    {
        $data = Request::unpack($data);//解包
        $request = null;
        $response = new Response();
        $response->setNodeId($this->config->getNodeId());
        //解数据
        if ($this->config->getSerializeType() == Config::SERIALIZE_TYPE_RAW) {
            $data = unserialize($data);
        } else {
            $data = json_decode($data, true);
            if (!is_array($data)) {
                $data = [];
            }
        }
        $request = new Request($data);//实例化请求数据
        if (!$request instanceof Request) {
            $request = new Request();
        }
        $request->setFd($fd);
        $request->setRawData($data);
        try {
            //onRequest 全局拦截 比如做权限或者签名验证
            $ret = $this->hookCallback($this->onRequest, $request, $response);
            if ($ret === false) {
                goto response;
            }
        } catch (\Throwable $throwable) {
            $response->setStatus($response::STATUS_SERVER_ERROR);
            $this->hookCallback($this->onException, $throwable, $request, $response);
            goto response;
        }

        if (isset($this->actionList[$request->getAction()])) {
            $call = $this->actionList[$request->getAction()];
            try {
                $this->hookCallback($call, $request, $response);
            } catch (\Throwable $throwable) {
                $response->setStatus($response::STATUS_SERVER_ERROR);
                $this->hookCallback($this->onException, $throwable, $request, $response);
                goto response;
            }
        } else {
            try {
                $response->setStatus($response::STATUS_SERVER_ACTION_MISS);
                $this->hookCallback($this->actionNotFound, $request, $response);
            } catch (\Throwable $throwable) {
                $response->setStatus($response::STATUS_SERVER_ERROR);
                $this->hookCallback($this->onException, $throwable, $request, $response);
                goto response;
            }
        }
        //最后的afterRequest 已经不影响服务逻辑，因此不主动改变status
        try {
            $this->hookCallback($this->afterRequest, $request, $response);
        } catch (\Throwable $throwable) {
            $this->hookCallback($this->onException, $throwable, $request, $response);
        }

        response:{
        if ($server->exist($fd)) {
            if ($this->config->getSerializeType() == Config::SERIALIZE_TYPE_RAW) {
                $response = serialize($response);
            } else {
                $response = $response->__toString();
            }
            $response = Request::pack($response);
            $server->send($fd, $response);
            $server->close($fd);
        }
    }
    }

    private function hookCallback($call, ...$arg)
    {
        if (is_callable($call)) {
            return call_user_func($call, ...$arg);
        } else {
            return null;
        }
    }
}