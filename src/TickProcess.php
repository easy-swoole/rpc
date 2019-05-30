<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\Process\AbstractProcess;
use Swoole\Coroutine\Socket;

class TickProcess extends AbstractProcess
{
    public function run($arg)
    {
        /** @var Config $config */
        $config = $arg['config'];
        $serviceList = $arg['serviceList'];
        $this->addTick(3*1000,function ()use($config,$serviceList){
            /** @var AbstractService $service */
            foreach ($serviceList as $service){
                try{
                    $node = new ServiceNode();
                    $node->setServiceVersion($service->version());
                    $node->setServiceName($service->serviceName());
                    $node->setServerIp($config->getServerIp());
                    $node->setServerPort($config->getListenPort());
                    $node->setLastHeartBeat(time());
                    $node->setNodeId($config->getNodeId());
                    $config->getNodeManager()->serviceNodeHeartBeat($node);
                }catch (\Throwable $throwable){
                    trigger_error("{$throwable->getMessage()} at file:{$throwable->getFile()} line:{$throwable->getLine()}");
                }
                try{
                    $service->onTick($config);
                }catch (\Throwable $throwable){
                    trigger_error("{$throwable->getMessage()} at file:{$throwable->getFile()} line:{$throwable->getLine()}");
                }
            }
        });
        if($config->getBroadcastConfig()->isEnableBroadcast()){
            //对外广播
            $this->addTick($config->getBroadcastConfig()->getInterval()*1000,function ()use($config,$serviceList){

            });
        }
        if($config->getBroadcastConfig()->isEnableListen())
        {
            go(function ()use($config){
                $socketServer = new Socket(AF_INET, SOCK_DGRAM);
                $socketServer->bind($config->getBroadcastConfig()->getListenAddress(), $config->getBroadcastConfig()->getListenPort());
                while (1){
                    $peer = null;
                    $data = $socketServer->recvfrom($peer);
                    if(empty($data)){
                        continue;
                    }
                }
            });
        }
    }
}