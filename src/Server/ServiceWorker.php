<?php


namespace EasySwoole\Rpc\Server;


use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\NetWork\Protocol;
use EasySwoole\Rpc\NetWork\Request;
use EasySwoole\Rpc\NetWork\Response;
use EasySwoole\Rpc\Service\AbstractService;
use Swoole\Coroutine\Socket;

class ServiceWorker extends AbstractTcpProcess
{
    /** @var Config */
    private $config;
    private $serviceList = [];

    function run($arg)
    {
        $this->config = $arg['config'];
        $this->serviceList = $arg['serviceList'];
        parent::run($arg);
    }

    function onAccept(Socket $socket)
    {
        $response = new Response();

        $header = $socket->recvAll(4, 1);
        if (strlen($header) != 4) {
            $response->setStatus($response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $response);
            return;
        }
        $allLength = Protocol::packDataLength($header);

        /** @var Config $config */
        $config = $this->getConfig()->getArg();
        if ($allLength > $config->getServer()->getMaxPackageSize()) {
            $response->setStatus($response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $response);
            return;
        }
        $data = $socket->recvAll($allLength, 3);
        if (strlen($data) != $allLength) {
            $response->setStatus($response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $response);
            return;
        }

        $request = json_decode($data, true);
        if (!$request instanceof Request) {
            $response->setStatus($response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $response);
            return;
        }
        if(isset($this->serviceList[$request->getService()])){
            /** @var AbstractService $service */
            //克隆模式，否则如果定义了成员属性会发生协程污染
            $service = clone $this->serviceList[$request->getService()];
            $service->__exec($request,$response,$socket);
        }else{
            $response->setStatus($response::STATUS_SERVICE_NOT_EXIST);
        }
        $this->reply($socket, $response);
    }

    protected function reply(Socket $clientSocket, Response $response)
    {
        $str = $response->__toString();
        $str = Protocol::pack($str);
        $clientSocket->sendAll($str);
        $clientSocket->close();
    }
}