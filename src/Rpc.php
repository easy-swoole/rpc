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
        $this->list[$service->serviceName()] = $service;
        TableManager::getInstance()->add($service->serviceName(),[
            'action'=>['type'=>Table::TYPE_STRING,'32'=>16],
            'success'=>['type'=>Table::TYPE_INT,'size'=>8],
            'fail'=>['type'=>Table::TYPE_INT,'size'=>8]
        ],64);
        return $this;
    }

    public function start()
    {

    }

    public function attachToServer(\swoole_server $server)
    {

    }

    public function generateProcess()
    {

    }

    private function check()
    {
        if(empty($this->config->getServerIp())){
            throw new Exception("serve ip is require");
        }
    }
}