<?php


namespace EasySwoole\Rpc\Server;


use EasySwoole\Component\Process\AbstractProcess;
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
        /** @var AbstractService $service */
        foreach ($this->serviceList as $service){
            $node = new ServiceNode();
            $node->setNodeId($this->config->getNodeId());
            $node->setIp($this->config->getServer()->getServerIp());
            $node->setPort($this->config->getServer()->getListenPort());
            $node->setService($service->serviceName());
            $node->setVersion($service->serviceVersion());
            $this->config->nodeManager()->alive($node);
        }
    }
}