<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\Process\Socket\TcpProcessConfig;
use EasySwoole\Rpc\Exception\Exception;
use EasySwoole\Rpc\Server\AssistWorker;
use EasySwoole\Rpc\Server\ServiceWorker;
use Swoole\Server;
use EasySwoole\Component\Process\Config as ProcessConfig;

class Rpc
{
    private $config;
    private $manager;

    function __construct(Config $config)
    {
        $this->manager = new Manager($config);
        $this->config = $config;
    }

    function serviceManager():Manager
    {
        return $this->manager;
    }

    function getConfig():Config
    {
        return $this->config;
    }

    function client():Client
    {
        return new Client($this->config->getNodeManager(),$this->config->getClient());
    }

    function attachServer(Server $server)
    {
        if(empty($this->config->getServer()->getServerIp())){
            throw new Exception("ServerIp is require for Rpc Server Config");
        }
        $serviceWorkers = $this->__getServiceWorker();
        /** @var ServiceWorker $value */
        foreach ($serviceWorkers as $serviceWorker) {
            $server->addProcess($serviceWorker->getProcess());
        }
        $server->addProcess($this->__getAssistWorker()->getProcess());
    }

    /** 不希望用户主动调用 */
    function __getServiceWorker():array
    {
        $list = [];
        for($i = 0;$i < $this->config->getServer()->getWorkerNum();$i++){
            $config = new TcpProcessConfig();
            $config->setProcessGroup("{$this->config->getServerName()}.Rpc");
            $config->setProcessName("{$this->config->getServerName()}.Rpc.Worker.{$i}");
            $config->setListenAddress($this->config->getServer()->getListenAddress());
            $config->setListenPort($this->config->getServer()->getListenPort());
            $config->setArg([
                'manager'=>$this->manager,
                'config'=>$this->config
            ]);
            $p = new ServiceWorker($config);
            $list[] = $p;
        }
        return  $list;
    }


    /** 不希望用户主动调用 */
    function __getAssistWorker():AssistWorker
    {
        $config = new ProcessConfig();
        $config->setProcessGroup("{$this->config->getServerName()}.Rpc");
        $config->setProcessName("{$this->config->getServerName()}.Rpc.AssistWorker");
        $config->setArg([
            'manager'=>$this->manager,
            'config'=>$this->config
        ]);
        return new AssistWorker($config);
    }
}