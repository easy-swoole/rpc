<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\TableManager;
use EasySwoole\Rpc\Exception\Exception;
use Swoole\Table;

class Rpc
{
    protected $config;
    protected $list = [];

    function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getConfig():Config
    {
        return $this->config;
    }

    public function add(AbstractService $service)
    {
        if(!isset($this->list[$service->serviceName()])){
            $this->list[$service->serviceName()] = $service;
            TableManager::getInstance()->add($service->serviceName(),[
                'success'=>['type'=>Table::TYPE_INT,'size'=>8],
                'fail'=>['type'=>Table::TYPE_INT,'size'=>8]
            ],64);
            $list = $service->actionList();
            foreach ($list as $action){
                TableManager::getInstance()->get($service->serviceName())->set($action,[
                    'success'=>0,
                    'fail'=>0,
                ]);
            }
        }
        return $this;
    }

    public function start()
    {

    }

    public function attachToServer(\swoole_server $server)
    {

    }

    public function generateProcess():array
    {
        $this->check();
        $ret = [];
        for ($i = 1;$i <= $this->getConfig()->getWorkerNum();$i++){
            $ret['worker'][] = new WorkerProcess("Rpc.Worker.{$i}",['config'=>$this->getConfig(),'serviceList'=>$this->list],false,2,true);
        }
        $ret['tickWorker'][] = new TickProcess("Rpc.TickWorker",['config'=>$this->getConfig(),'serviceList'=>$this->list],false,2,true);
        return $ret;
    }

    private function check()
    {
        if(empty($this->config->getServerIp())){
            throw new Exception("serve ip is require");
        }
        if(empty($this->config->getNodeManager())){
            throw new Exception("serve NodeManager require");
        }
    }

    function client():Client
    {
        return new Client($this->getConfig()->getNodeManager());
    }
}