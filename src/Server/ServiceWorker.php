<?php


namespace EasySwoole\Rpc\Server;


use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\NetWork\Protocol;
use EasySwoole\Rpc\NetWork\Request;
use EasySwoole\Rpc\NetWork\Response;
use Swoole\Coroutine\Socket;

class ServiceWorker extends AbstractTcpProcess
{
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

        // todo 调用 service
    }

    protected function reply(Socket $clientSocket, Response $response)
    {
        $str = $response->__toString();
        $str = Protocol::pack($str);
        $clientSocket->sendAll($str);
        $clientSocket->close();
    }
}