<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\Process\AbstractProcess;
use Swoole\Coroutine\Socket;

class WorkerProcess extends AbstractProcess
{

    public function run($arg)
    {
        /** @var Config $config */
        $config  = $arg['config'];
        $serviceList = $arg['serviceList'];
        $socket = new Socket(AF_INET,SOCK_STREAM,0);
        $socket->setOption(SOL_SOCKET,SO_REUSEPORT,true);
        $socket->setOption(SOL_SOCKET,SO_REUSEADDR,true);
        $ret = $socket->bind($config->getListenAddress(),$config->getListenPort());
        if(!$ret){
            trigger_error("Rpc bind {$config->getListenAddress()}:{$config->getListenPort()} fail");
            return;
        }
        $ret = $socket->listen(2048);
        if(!$ret){
            trigger_error("Rpc listen {$config->getListenAddress()}:{$config->getListenPort()} fail");
            return;
        }
        while (1){
            $client = $socket->accept(-1);
            if($client){
                go(function ()use($client,$arg,$serviceList){
                    $reply = new Response();
                    $header = $client->recvAll(4,1);
                    if(strlen($header) != 4){
                        $reply->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
                        $this->reply($client,$reply);
                        return;
                    }
                    $allLength = Protocol::packDataLength($header);
                    $data = $client->recvAll($allLength,3);
                    if(strlen($data) != $allLength){
                        $reply->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
                        $this->reply($client,$reply);
                        return;
                    }
                    $command = unserialize($data);
                    if(!$command instanceof Command){
                        $reply->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
                        $this->reply($client,$reply);
                        return;
                    }

                    switch ($command->getCommand()){
                        case Command::SERVICE_CALL:{
                            $request = $command->getRequest();
                            if(!$request){
                                $reply->setStatus(Response::STATUS_ILLEGAL_PACKAGE);
                                $this->reply($client,$reply);
                            }else{
                                if(isset($serviceList[$request->getServerName()])){
                                    /** @var AbstractService $service */
                                    $service = $serviceList[$request->getServerName()];
                                    $service->__hook($request);
                                    $this->reply($client,$reply);
                                }else{
                                    $reply->setStatus(Response::STATUS_SERVICE_NOT_EXIST);
                                    $this->reply($client,$reply);
                                }
                            }
                            break;
                        }
                    }
                });
            }
        }
    }

    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }

    private function reply($client,Response $response)
    {
        $str = $response->__toString();
        $str = Protocol::pack($str);
        $str->sendAll($str);
        $client->close();
    }
}