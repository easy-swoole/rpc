<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\Process\AbstractProcess;

class TickProcess extends AbstractProcess
{

    public function run($arg)
    {
        /** @var Config $config */
        $config = $arg['config'];
        $serviceList = $arg['serviceList'];
        $this->addTick(5*1000,function ()use($config,$serviceList){
            /** @var AbstractService $service */
            foreach ($serviceList as $service){
                try{
                    $node = new ServiceNode();
                    $node->setServiceVersion($service->version());
                    $node->setServiceName($service->serviceName());
                    $node->setServiceIp($config->getServerIp());
                    $node->setServicePort($config->getListenPort());
                    $node->setLastHeartBeat(time());
                    $config->getNodeManager()->serviceNodeHeartBeat($node);
                }catch (\Throwable $throwable){
                    trigger_error("{$throwable->getMessage()} at file:{$throwable->getFile()} line:{$throwable->getLine()}");
                }
                try{
                    $service->__onTick($config);
                }catch (\Throwable $throwable){
                    trigger_error("{$throwable->getMessage()} at file:{$throwable->getFile()} line:{$throwable->getLine()}");
                }
            }
        });
    }

    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}