<?php


namespace EasySwoole\Rpc\Server;


use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Timer;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Manager;
use EasySwoole\Rpc\Network\UdpClient;
use EasySwoole\Rpc\Protocol\UdpPack;
use EasySwoole\Rpc\Utility\Openssl;
use Swoole\Coroutine;

class AssistWorker extends AbstractProcess
{
    /** @var Config */
    private $rpcConfig;
    /** @var Manager */
    private $serviceManager;

    function run($arg)
    {
        $this->rpcConfig = $arg['config'];
        $this->serviceManager = $arg['manager'];
        //服务自刷新。
        $this->serviceAlive();
        Timer::getInstance()->loop($this->rpcConfig->getAssist()->getAliveInterval(),function (){
            $this->serviceAlive();
        });

        $udpServiceFinderConfig = $this->rpcConfig->getAssist()->getUdpServiceFinder();
        if($udpServiceFinderConfig->isEnableBroadcast()){
            $udpClient = new UdpClient($udpServiceFinderConfig,$this->rpcConfig->getNodeId());
            Timer::getInstance()->loop($udpServiceFinderConfig->getBroadcastInterval(),function ()use($udpClient){
                $list = $this->serviceManager->getServiceNodes();
                /** @var ServiceNode $node */
                foreach ($list as $node){
                    $pack = new UdpPack();
                    if($this->serviceManager->isAlive($node->getService())){
                        $pack->setOp(UdpPack::OP_ALIVE);
                    }else{
                        $pack->setOp(UdpPack::OP_SHUTDOWN);
                    }
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
                $socketServer->setOption(SOL_SOCKET, SO_REUSEPORT, 1);
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

    protected function onShutDown()
    {
        foreach ($this->serviceManager->getServiceNodes() as $node){
            $this->rpcConfig->getNodeManager()->offline($node);
        }
        //对外广播节点下线。
        $udpClient = new UdpClient($this->rpcConfig->getAssist()->getUdpServiceFinder(),$this->rpcConfig->getNodeId());
        $list = $this->serviceManager->getServiceNodes();
        foreach ($list as $node){
            $pack = new UdpPack();
            $pack->setOp(UdpPack::OP_SHUTDOWN);
            $pack->setArg($node);
            $udpClient->broadcast($pack);
        }
    }

    protected function onException(\Throwable $throwable, ...$args)
    {
        $call = $this->rpcConfig->getOnException();
        if(is_callable($call)){
            call_user_func($call,$throwable);
        }else{
            throw $throwable;
        }
    }

    private function serviceAlive()
    {
        foreach ($this->serviceManager->getServiceNodes() as $node){
            if($this->serviceManager->isAlive($node->getService())){
                $this->rpcConfig->getNodeManager()->alive($node);
            }else{
                $this->rpcConfig->getNodeManager()->offline($node);
            }
        }
    }

    private function handleUdpPack(UdpPack $pack)
    {
        /** 忽略过期数据 */
        if(time() - $pack->getPackTime() > 5){
            return;
        }

        /** 忽略自身广播 */
        if($pack->getFromNodeId() === $this->rpcConfig->getNodeId()){
            return;
        }

        switch ($pack->getOp()){
            case UdpPack::OP_ALIVE:{
                $node = new ServiceNode($pack->getArg());
                $this->rpcConfig->getNodeManager()->alive($node);
                break;
            }
            case UdpPack::OP_SHUTDOWN:{
                $node = new ServiceNode($pack->getArg());
                $this->rpcConfig->getNodeManager()->offline($node);
                break;
            }
        }
    }
}