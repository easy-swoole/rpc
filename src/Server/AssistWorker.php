<?php


namespace EasySwoole\Rpc\Server;


use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Service\AbstractService;

class AssistWorker extends AbstractProcess
{
    /** @var Config */
    private $config;
    private $serviceList = [];

    function run($arg)
    {
        $this->config = $arg['config'];
        $this->serviceList = $arg['serviceList'];
        //服务自刷新。
        $this->serviceAlive();
        Timer::getInstance()->loop($this->config->getAssist()->getAliveInterval(),function (){
            $this->serviceAlive();
        });

        if($this->config->getAssist()->getUdpServiceFinder()->isEnableBroadcast()){
            Timer::getInstance()->loop($this->config->getAssist()->getUdpServiceFinder()->getBroadcastInterval(),function (){

            });
        }
    }


    private function serviceAlive()
    {
        foreach ($this->getServiceNodes() as $node){
            $this->config->nodeManager()->alive($node);
        }
    }

    private function getServiceNodes():array
    {
        $list = [];
        /** @var AbstractService $service */
        foreach ($this->serviceList as $service){
            $node = new ServiceNode();
            $node->setNodeId($this->config->getNodeId());
            $node->setIp($this->config->getServer()->getServerIp());
            $node->setPort($this->config->getServer()->getListenPort());
            $node->setService($service->serviceName());
            $node->setVersion($service->serviceVersion());
            $list[] = $node;
        }
        return $list;
    }
}