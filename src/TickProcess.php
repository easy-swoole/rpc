<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\Process\AbstractProcess;
use Swoole\Coroutine;
use Swoole\Coroutine\Client;
use Swoole\Coroutine\Socket;

class TickProcess extends AbstractProcess
{
    //创建自定义进程回调
    public function run($arg)
    {
        /** @var Config $config */
        $config = $arg['config'];//配置
        $serviceList = $arg['serviceList'];//服务
        //指定了ip的情况下，允许主动刷新注册自己的节点信息
        if(!empty($config->getServerIp())){
            $this->addTick(3 * 1000, function () use ($config, $serviceList) {
                //每3秒刷新本节点各个服务的心跳时间
                /** @var AbstractService $service */
                foreach ($serviceList as $service) {//遍历本节点的服务列表
                    try {
                        $node = new ServiceNode();
                        $node->setServiceVersion($service->version());
                        $node->setServiceName($service->serviceName());
                        $node->setServerIp($config->getServerIp());
                        $node->setServerPort($config->getListenPort());
                        $node->setLastHeartBeat(time());
                        $node->setNodeId($config->getNodeId());
                        $config->getNodeManager()->serviceNodeHeartBeat($node);
                    } catch (\Throwable $throwable) {
                        $this->onException($throwable);
                    }
                }
            });
        }
        if ($config->getBroadcastConfig()->isEnableBroadcast()) {//对外广播
            $this->udpBroadcast($config, $serviceList, BroadcastCommand::COMMAND_HEART_BEAT);
            $this->addTick($config->getBroadcastConfig()->getInterval() * 1000, function () use ($config, $serviceList) {
                $this->udpBroadcast($config, $serviceList, BroadcastCommand::COMMAND_HEART_BEAT);
            });
        }
        if ($config->getBroadcastConfig()->isEnableListen()) {
            Coroutine::create(function () use ($config) {
                $openssl = null;
                $secretKey = $config->getBroadcastConfig()->getSecretKey();
                if (!empty($secretKey)) {
                    $openssl = new Openssl($secretKey);
                }
                $socketServer = new Socket(AF_INET, SOCK_DGRAM);
                $socketServer->bind($config->getBroadcastConfig()->getListenAddress(), $config->getBroadcastConfig()->getListenPort());
                while (1) {
                    $peer = null;
                    $data = $socketServer->recvfrom($peer);
                    if (empty($data)) {
                        continue;
                    }
                    if ($openssl) {
                        $data = $openssl->decrypt($data);
                    }
                    $data = unserialize($data);
                    if ($data instanceof BroadcastCommand) {
                        if(time() - $data->getRequestTime() > 5){
                            continue;
                        }
                        $node = $data->getServiceNode();
                        if(empty($node->getServerIp())){
                            $node->setServerIp($peer['address']);
                        }
                        if ($data->getCommand() == $data::COMMAND_HEART_BEAT) {
                            $config->getNodeManager()->serviceNodeHeartBeat($node);
                        } else if ($data->getCommand() == $data::COMMAND_OFF_LINE) {
                            $config->getNodeManager()->deleteServiceNode($node);
                        }
                    }
                }
            });
        }
    }

    //进程关闭时回调
    protected function onShutDown()
    {
        /** @var Config $config */
        $config = $this->getConfig()->getArg()['config'];
        $serviceList = $this->getConfig()->getArg()['serviceList'];
        /** @var AbstractService $service */
        foreach ($serviceList as $service) {
            //遍历本节点的服务列表
            try {
                $node = new ServiceNode();
                $node->setServiceVersion($service->version());
                $node->setServiceName($service->serviceName());
                $node->setNodeId($config->getNodeId());
                $config->getNodeManager()->deleteServiceNode($node);
            } catch (\Throwable $throwable) {
                $this->onException($throwable);
            }
        }
        $this->udpBroadcast($config, $serviceList, BroadcastCommand::COMMAND_OFF_LINE);
    }

    //udp广播
    protected function udpBroadcast(Config $config, array $serviceList, int $command)
    {
        $openssl = null;
        $secretKey = $config->getBroadcastConfig()->getSecretKey();
        if (!empty($secretKey)) {
            $openssl = new Openssl($secretKey);
        }
        $client = new Client(SWOOLE_UDP);
        //创建节点信息
        $node = new ServiceNode();
        $node->setServerPort($config->getListenPort());
        $node->setServerIp($config->getServerIp());
        $node->setNodeId($config->getNodeId());
        $node->setLastHeartBeat(time());
        //构建命令
        $broadcastCommand = new BroadcastCommand();
        $broadcastCommand->setCommand($command);
        $broadcastCommand->setServiceNode($node);
        /**
         * @var  $serviceName
         * @var AbstractService $service
         */
        foreach ($serviceList as $serviceName => $service) {
            $node->setServiceName($serviceName);
            $node->setServiceVersion($service->version());
            $data = serialize($broadcastCommand);
            if ($openssl) {
                $data = $openssl->encrypt($data);
            }
            //遍历广播地址发送
            foreach ($config->getBroadcastConfig()->getBroadcastAddress() as $address) {
                $address = explode(':', $address);
                $client->sendto($address[0], $address[1], $data);
            }
        }
        $client->close();
        unset($client);
    }

    protected function onException(\Throwable $throwable, ...$args)
    {
        /** @var Config $config */
        $config = $this->getConfig()->getArg()['config'];
        if ($config->getOnException()) {
            call_user_func($config->getOnException(),$throwable);
        } else {
            throw $throwable;
        }
    }
}