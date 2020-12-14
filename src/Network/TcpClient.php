<?php


namespace EasySwoole\Rpc\Network;


use EasySwoole\Rpc\Server\ServiceNode;
use Swoole\Coroutine\Client;

class TcpClient
{
    private $client;

    function __construct(int $maxPackSize,float $timeout)
    {
        $this->client = new Client(SWOOLE_TCP);
        $this->client->set([
            'open_length_check' => true,
            'package_length_type'   => 'N',
            'package_length_offset' => 0,
            'package_body_offset'   => 4,
            'package_max_length'    => $maxPackSize,
            'timeout'=>$timeout
        ]);
    }

    function connect(ServiceNode $node,float $timeout = null)
    {
        return $this->client->connect($node->getIp(),$node->getPort(),$timeout);
    }

    function sendRequest(Request $request)
    {
        $data = $request->__tostring();
        $data = Protocol::pack($data);
        $len = strlen($data);
        if($this->client->send($data) !== $len){
            return false;
        }else{
            return true;
        }
    }

    function recv(float $timeout = null)
    {
        $res = new Response();
        $data = $this->client->recv($timeout);
        if($data){
            $data = Protocol::unpack($data);
            $json = json_decode($data,true);
            if(is_array($json)){
                if(isset($json['status'])){
                    $res->setStatus($json['status']);
                    $res->setMsg($json['msg']??null);
                    $res->setResult($json['result']??null);
                }else{
                    $res->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
                }
            }else{
                $res->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
            }
        }else{
            $res->setStatus(Response::STATUS_SERVER_TIMEOUT);
        }
        return $res;
    }

    function __destruct()
    {
        if($this->client->isConnected()){
            $this->client->close();
        }
        unset($this->client);
    }
}