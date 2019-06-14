<?php


namespace EasySwoole\Rpc;

use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use EasySwoole\Component\TableManager;
use Swoole\Coroutine\Socket;

class WorkerProcess extends AbstractTcpProcess
{
    function onAccept(Socket $socket)
    {
        /** @var Config $config */
        $config = $this->getConfig()->getArg()['config'];
        $serviceList = $this->getConfig()->getArg()['serviceList'];
        $reply = new Response();
        $reply->setNodeId($config->getNodeId());//回复设置当前节点
        $header = $socket->recvAll(4, 1);
        if (strlen($header) != 4) {
            $reply->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $reply);
            return;
        }
        $allLength = Protocol::packDataLength($header);
        if ($allLength >= $config->getMaxPackage()) {
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
                    if (isset($serviceList[$request->getServiceName()])) {
                        $client = new SocketClient();
                        $client->setSocket($client);
                        /** @var AbstractService $service */
                        $service = $serviceList[$request->getServiceName()];
                        $service->__hook($request, $reply, $client);
                        $this->reply($socket, $reply);
                    } else {
                        $reply->setStatus(Response::STATUS_SERVICE_NOT_EXIST);
                        $this->reply($socket, $reply);
                    }
                    break;
                }
            case Command::SERVICE_STATUS:
                {
                    $ret = [];
                    foreach ($serviceList as $serviceName => $item) {
                        $table = TableManager::getInstance()->get($serviceName);
                        if ($table) {
                            foreach ($table as $action => $info) {
                                $ret[$serviceName][$action] = $info;
                            }
                        }
                    }
                    $reply->setResult($ret);
                    $this->reply($socket, $reply);
                    break;
                }
        }
    }

    private function reply(Socket $clientSocket, Response $response)
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
        if ($config->getTrigger()) {
            $config->getTrigger()->throwable($throwable);
        } else {
            throw $throwable;
        }
    }
}