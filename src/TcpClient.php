<?php


namespace EasySwoole\Rpc;


use Swoole\Coroutine\Client as CoClient;

class TcpClient
{
    private $client;
    function __construct(ServerNode $node,float $timeout = 3.0)
    {
        $this->client = $this->createNodeClient($node,$timeout);
    }

    function sendCommand(Command $command)
    {
        if($this->client && $this->client->isConnected()){
            $str = $command->__toString();
            $str = Protocol::pack($str);
            return $this->client->send($str);
        }else{
            return false;
        }
    }

    function recv(float $timeout = 3.0):Response
    {
        if($this->client && $this->client->isConnected()){
            $data = $this->client->recv($timeout);
            if(!empty($data)){
                $data = Protocol::unpack($data);
                $json = json_decode($data,true);
                if(is_array($json)){
                    return new Response($json);
                }else{
                    return new Response([
                        'status'=>Response::STATUS_SERVICE_ERROR,
                        'message'=>$data
                    ]);
                }
            }else{
                return new Response([
                    'status'=>Response::STATUS_SERVER_TIMEOUT
                ]);
            }
        }else{
            return new Response([
                'status'=>Response::STATUS_CONNECT_TIMEOUT
            ]);
        }
    }

    private function createNodeClient(ServerNode $serverNode,float $timeout = 3.0):?CoClient
    {
        $client = new CoClient(SWOOLE_TCP);
        $client->set([
            'open_length_check' => true,
            'package_length_type'   => 'N',
            'package_length_offset' => 0,
            'package_body_offset'   => 4,
            'package_max_length'    => 1024*1024
        ]);
        if($client->connect($serverNode->getServerIp(),$serverNode->getServerPort(),$timeout)){
            return $client;
        }else{
            return null;
        }
    }
}