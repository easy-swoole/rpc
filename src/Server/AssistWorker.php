<?php


namespace EasySwoole\Rpc\Server;


use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Network\UdpClient;
use EasySwoole\Rpc\Protocol\UdpPack;
use EasySwoole\Rpc\Service\AbstractService;
use EasySwoole\Rpc\Utility\Openssl;
use Swoole\Coroutine;

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

        $udpServiceFinderConfig = $this->config->getAssist()->getUdpServiceFinder();
        if($udpServiceFinderConfig->isEnableBroadcast()){
            $udpClient = new UdpClient($udpServiceFinderConfig,$this->config->getNodeId());
            Timer::getInstance()->loop($udpServiceFinderConfig->getBroadcastInterval(),function ()use($udpClient){
                $list = $this->getServiceNodes();
                foreach ($list as $node){
                    $pack = new UdpPack();
                    $pack->setOp(UdpPack::OP_ALIVE);
                    $pack->setArg($node);
                    $udpClient->broadcast($pack);
                }
            });
        }

        if($udpServiceFinderConfig->isEnableListen()){
            Coroutine::create(function ()use($udpServiceFinderConfig){
                $socketServer = new Coroutine\Socket(AF_INET, SOCK_DGRAM);
                $address = $udpServiceFinderConfig->getListenAddress();
                $port = $udpServiceFinderConfig->getListenPort();
                $socketServer->bind($address,$port);
                $openssl = null;
                $secretKey = $udpServiceFinderConfig->getEncryptKey();
                if (!empty($secretKey)) {
                    $openssl = new Openssl($secretKey);
                }
                while (true){
                    $peer = null;
                    $data = $socketServer->recvfrom($peer);
                    if(empty($data)){
                        continue;
                    }
                    if($openssl){
                        $data = $openssl->decrypt($data);
                    }
                    $json = json_decode($data,true);
                    if(!is_array($json)){
                        continue;
                    }
                    $pack = new UdpPack($json);
                    $this->handleUdpPack($pack);
                }
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

    private function handleUdpPack(UdpPack $pack)
    {
        /** 忽略过期数据 */
        if(time() - $pack->getPackTime() > 5){
            return;
        }

        /** 忽略自身广播 */
        if($pack->getFromNodeId() === $this->config->getNodeId()){
            return;
        }

        switch ($pack->getOp()){
            case UdpPack::OP_ALIVE:{
                $node = new ServiceNode($pack->getArg());
                $this->config->nodeManager()->alive($node);
                break;
            }
            case UdpPack::OP_SHUTDOWN:{
                $node = new ServiceNode($pack->getArg());
                $this->config->nodeManager()->deleteNode($node);
                break;
            }
        }
    }
}