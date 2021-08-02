<?php


namespace EasySwoole\Rpc\Server;


use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Manager;
use EasySwoole\Rpc\Protocol\Protocol;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Service\AbstractService;
use Swoole\Coroutine\Socket;

class ServiceWorker extends AbstractTcpProcess
{
    /** @var Config */
    private $rpcConfig;
    /** @var Manager */
    private $serviceManager;

    public function run($arg)
    {
        $this->rpcConfig = $arg['config'];
        $this->serviceManager = $arg['manager'];
        ini_set('memory_limit', $this->rpcConfig->getMaxMem());
        parent::run($arg);
    }

    public function onAccept(Socket $socket)
    {
        $response = new Response();

        $header = $socket->recvAll(4, $this->rpcConfig->getServer()->getNetworkReadTimeout());
        if (strlen($header) != 4) {
            $response->setStatus($response::STATUS_PACKAGE_READ_TIMEOUT);
            $this->reply($socket, $response);
            return;
        }

        $allLength = Protocol::packDataLength($header);
        if ($allLength > $this->rpcConfig->getServer()->getMaxPackageSize()) {
            $response->setStatus($response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $response);
            return;
        }
        $data = $socket->recvAll($allLength, $this->rpcConfig->getServer()->getNetworkReadTimeout());
        if (strlen($data) != $allLength) {
            $response->setStatus($response::STATUS_PACKAGE_READ_TIMEOUT);
            $this->reply($socket, $response);
            return;
        }
        $request = json_decode($data, true);
        if (!is_array($request)) {
            $response->setStatus($response::STATUS_ILLEGAL_PACKAGE);
            $this->reply($socket, $response);
            return;
        }
        $request = new Request($request);
        $serviceList = $this->serviceManager->getServiceRegisterArray();
        try {
            if (isset($serviceList[$request->getService()])) {
                /** @var AbstractService $service */
                //克隆模式，否则如果定义了成员属性会发生协程污染
                $service = clone $serviceList[$request->getService()];
                if ($this->serviceManager->isAlive($service->serviceName())) {
                    $service->__exec($request, $response, $socket);
                } else {
                    $response->setStatus($response::STATUS_SERVICE_SHUTDOWN);
                }
            } else {
                $response->setStatus($response::STATUS_SERVICE_NOT_EXIST);
            }
        } catch (\Throwable $throwable) {
            $response->setStatus(Response::STATUS_SERVICE_ERROR);
            $this->onException($throwable);
        }
        $this->reply($socket, $response);
    }

    protected function reply(Socket $clientSocket, Response $response)
    {
        $str = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $str = Protocol::pack($str);
        $clientSocket->sendAll($str);
        $clientSocket->close();
    }

    protected function onException(\Throwable $throwable, ...$args)
    {
        $call = $this->rpcConfig->getOnException();
        if (is_callable($call)) {
            call_user_func($call, $throwable);
        } else {
            throw $throwable;
        }
    }
}