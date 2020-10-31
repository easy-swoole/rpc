<?php


namespace EasySwoole\Rpc;

use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use EasySwoole\Pool\AbstractPool;
use Swoole\Coroutine\Socket;
use Swoole\Table;

class WorkerProcess extends AbstractTcpProcess
{
    /** @var Config */
    protected $rpcConfig;
    protected $serviceList;
    protected $statisticsTable;
    function run($arg)
    {
        $this->rpcConfig = $this->getConfig()->getArg()['config'];
        $this->serviceList = $this->getConfig()->getArg()['serviceList'];
        $this->statisticsTable = $this->getConfig()->getArg()['statisticsTable'];
        parent::run($arg);
    }

    function onAccept(Socket $socket)
    {
        $reply = new Response();
        $reply->setNodeId($this->rpcConfig->getNodeId());//回复设置当前节点
        $header = $socket->recvAll(4, 1);
        if (strlen($header) != 4) {
            $reply->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $reply);
            return;
        }
        $allLength = Protocol::packDataLength($header);
        if ($allLength >= $this->rpcConfig->getMaxPackage()) {
            $socket->close();
            //恶意包，直接断开不回复
            return;
        }
        $data = $socket->recvAll($allLength, 3);
        if (strlen($data) != $allLength) {
            $reply->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $reply);
            return;
        }
        $command = json_decode($data, true);
        if (is_array($command)) {
            $command = new Command($command);
        } else {
            $reply->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $reply);
            return;
        }
        $request = $command->getRequest();
        if (!$request) {
            $reply->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $reply);
            return;
        }
        switch ($command->getCommand()) {
            case Command::SERVICE_CALL:
                {
                    if (isset($this->serviceList[$request->getServiceName()])) {
                        /**@var AbstractPool $pool */
                        $pool = $this->serviceList[$request->getServiceName()];
                        /** @var AbstractService $service */
                        $service = $pool->getObj();
                        $service->__hook($request, $reply, $socket);

                        /** @var Table $table */
                        $table = $this->statisticsTable[$request->getServiceName()];
                        if ($reply->getStatus() === Response::STATUS_OK) {
                            $table->incr($request->getAction(), 'success');
                        } else {
                            $table->incr($request->getAction(), 'fail');
                        }
                        $this->reply($socket, $reply,$request);
                        $pool->recycleObj($service);
                    } else {
                        $reply->setStatus(Response::STATUS_SERVICE_NOT_EXIST);
                        $this->reply($socket, $reply,$request);
                    }
                    break;
                }
            case Command::SERVICE_STATUS:
                {
                    $ret = [];
                    /**@var AbstractPool $item */
                    foreach ($this->serviceList as $serviceName => $item) {
                        $ret['pool'][$serviceName] = $item->status();
                        $table = $this->statisticsTable[$serviceName];
                        foreach ($table as $action => $info) {
                            $ret['services'][$serviceName][$action] = $info;
                        }
                    }
                    $reply->setResult($ret);
                    $this->reply($socket, $reply,$request);
                    break;
                }
        }
    }

    protected function reply(Socket $clientSocket, Response $response,?Request $request = null)
    {
        $str = $response->__toString();
        $str = Protocol::pack($str);
        $clientSocket->sendAll($str);
        $clientSocket->close();
    }

    protected function onException(\Throwable $throwable, ...$args)
    {
        /** @var Config $config */
        $config = $this->getConfig()->getArg()['config'];
        if ($config->getOnException()) {
            call_user_func($config->getOnException(), $throwable);
        } else {
            throw $throwable;
        }
    }
}