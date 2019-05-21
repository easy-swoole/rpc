<?php


namespace EasySwoole\Rpc;


use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use Swoole\Coroutine\Client as CoClient;

class Client
{
    protected $nodeManager;

    function __construct(NodeManagerInterface $manager)
    {
        $this->nodeManager = $manager;
    }

    function serverStatus(ServerNode $serverNode):?array
    {
        $command = new Command();
        $command->setCommand(Command::SERVICE_STATUS);
        $client = $this->createNodeClient($serverNode);
        if($client){
            $this->sendCommand($client,$command);
            $ret = $this->recv($client);
            if($ret){
                return $ret->getResult();
            }
        }
        return null;
    }


    private function createNodeClient(ServerNode $serverNode):?CoClient
    {
        $client = new CoClient(SWOOLE_TCP);
        $client->set([
            'open_length_check' => true,
            'package_length_type'   => 'N',
            'package_length_offset' => 0,
            'package_body_offset'   => 4,
            'package_max_length'    => 1024*1024
        ]);
        if($client->connect($serverNode->getServerIp(),$serverNode->getServerPort(),3)){
            return $client;
        }else{
            return null;
        }
    }

    private function sendCommand(CoClient $client,Command $command)
    {
        $str = $command->__toString();
        $str = Protocol::pack($str);
        return $client->send($str);
    }

    private function recv(CoClient $client,float $timeout = 3.0):?Response
    {
        $data = $client->recv($timeout);
        if(!empty($data)){
            $data = Protocol::unpack($data);
            $data = json_decode($data,true);
            if(is_array($data)){
                return new Response($data);
            }
        }
        return null;
    }
}