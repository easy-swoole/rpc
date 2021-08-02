<?php


namespace EasySwoole\Rpc\Network;


use EasySwoole\Rpc\Protocol\Protocol;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Server\ServiceNode;
use Swoole\Coroutine\Client;

class TcpClient
{
    private $client;

    public function __construct(int $maxPackSize, float $timeout)
    {
        $this->client = new Client(SWOOLE_TCP);
        $this->client->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
            'package_max_length' => $maxPackSize,
            'timeout' => $timeout
        ]);
    }

    public function connect(ServiceNode $node, float $timeout = null)
    {
        return $this->client->connect($node->getIp(), $node->getPort(), $timeout);
    }

    public function sendRequest(Request $request)
    {
        $data = $request->__tostring();
        $data = Protocol::pack($data);
        $len = strlen($data);
        if ($this->client->send($data) !== $len) {
            return false;
        } else {
            return true;
        }
    }

    public function recv(float $timeout = null)
    {
        $res = new Response();
        $data = $this->client->recv($timeout);
        if ($data) {
            $data = Protocol::unpack($data);
            $json = json_decode($data, true);
            if (is_array($json)) {
                $res->restore($json);
            } else {
                $res->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
            }
        } else {
            $res->setStatus(Response::STATUS_SERVER_TIMEOUT);
        }
        return $res;
    }

    public function __destruct()
    {
        if ($this->client->isConnected()) {
            $this->client->close();
        }
        unset($this->client);
    }
}