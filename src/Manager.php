<?php


namespace EasySwoole\Rpc;


use EasySwoole\Rpc\Network\UdpClient;
use EasySwoole\Rpc\Protocol\UdpPack;
use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Service\AbstractService;
use Swoole\Table;

class Manager
{
    private $serviceTable;
    private $config;
    private $serviceRegisterArray = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->serviceTable = new Table(2048);
        $this->serviceTable->column('isOnline', Table::TYPE_INT, 1);
        $this->serviceTable->create();
    }

    public function onlineStatus(): array
    {
        $list = [];
        foreach ($this->serviceTable as $key => $value){
            $list[$key] = (bool)$value['isOnline'];
        }
        return $list;
    }

    public function offline(?string $service = null)
    {
        if($service === null){
            $list = [$service];
        }else{
            $list = array_keys($this->serviceRegisterArray);
        }
        $udpClient = new UdpClient($this->config->getAssist()->getUdpServiceFinder(),$this->config->getNodeId());
        foreach ($list as $service){
            $node = $this->getServiceNode($service);
            $this->serviceTable->set($service,['isOnline'=>0]);
            $this->config->getNodeManager()->offline($node);
            if($this->config->getAssist()->getUdpServiceFinder()->isEnableBroadcast()){
                $pack = new UdpPack();
                $pack->setOp(UdpPack::OP_SHUTDOWN);
                $pack->setArg($node);
                $udpClient->broadcast($pack);
            }
        }
    }

    public function isAlive(string $service): bool
    {
        $info = $this->serviceTable->get($service);
        if($info && $info['isOnline'] == 1){
            return true;
        }
        return false;
    }

    public function online(?string $service = null)
    {
        if($service === null){
            $list = [$service];
        }else{
            $list = array_keys($this->serviceRegisterArray);
        }
        $udpClient = new UdpClient($this->config->getAssist()->getUdpServiceFinder(),$this->config->getNodeId());
        foreach ($list as $service){
            $node = $this->getServiceNode($service);
            $this->serviceTable->set($service,['isOnline'=>1]);
            $this->config->getNodeManager()->alive($node);
            if($this->config->getAssist()->getUdpServiceFinder()->isEnableBroadcast()){
                $pack = new UdpPack();
                $pack->setOp(UdpPack::OP_ALIVE);
                $pack->setArg($node);
                $udpClient->broadcast($pack);
            }
        }
    }

    public function getServiceNode(string $service): ?ServiceNode
    {
        if(isset($this->serviceRegisterArray[$service])){
            $service = $this->serviceRegisterArray[$service];
            $node = new ServiceNode();
            $node->setNodeId($this->config->getNodeId());
            $node->setIp($this->config->getServer()->getServerIp());
            $node->setPort($this->config->getServer()->getListenPort());
            $node->setService($service->serviceName());
            $node->setVersion($service->serviceVersion());
            return $node;
        }else{
            return null;
        }
    }

    public function getServiceNodes(): array
    {
        $list = [];
        /** @var AbstractService $service */
        foreach ($this->serviceRegisterArray as $service){
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

    /**
     * @return array
     */
    public function getServiceRegisterArray(): array
    {
        return $this->serviceRegisterArray;
    }

    public function addService(AbstractService $service): Manager
    {
        $this->serviceRegisterArray[$service->serviceName()] = $service;
        $this->serviceTable->set($service->serviceName(),['isOnline'=>1]);
        return $this;
    }
}