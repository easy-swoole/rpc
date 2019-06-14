<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\Process\Socket\TcpProcessConfig;
use EasySwoole\Component\Singleton;
use EasySwoole\Component\TableManager;
use EasySwoole\Rpc\Exception\Exception;
use Swoole\Table;

class Rpc
{
    protected $config;
    protected $list = [];

    use Singleton;

    function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * 注册服务
     * @param AbstractService $service
     * @return $this
     */
    public function add(AbstractService $service)
    {
        if (!isset($this->list[$service->serviceName()])) {
            $this->list[$service->serviceName()] = $service;
            TableManager::getInstance()->add($service->serviceName(), [
                'success' => ['type' => Table::TYPE_INT, 'size' => 8],
                'fail' => ['type' => Table::TYPE_INT, 'size' => 8]
            ], 64);//创建服务统计表
            $list = $service->actionList();
            foreach ($list as $action) {//初始化每个接口
                TableManager::getInstance()->get($service->serviceName())->set($action, [
                    'success' => 0,
                    'fail' => 0,
                ]);
            }
        }
        return $this;
    }

    public function attachToServer(\swoole_server $server)
    {
        $list = $this->generateProcess();
        foreach ($list['worker'] as $p) {
            $server->addProcess($p->getProcess());
        }
        foreach ($list['tickWorker'] as $p) {
            $server->addProcess($p->getProcess());
        }
    }

    public function generateProcess(): array
    {
        $this->check();
        $ret = [];
        for ($i = 1; $i <= $this->getConfig()->getWorkerNum(); $i++) {
            $config = new TcpProcessConfig();
            $config->setProcessName("Rpc.Worker.{$i}");
            $config->setListenAddress($this->getConfig()->getListenAddress());
            $config->setListenPort($this->getConfig()->getListenPort());
            $config->setArg(['config' => $this->getConfig(), 'serviceList' => $this->list]);
            $ret['worker'][] = new WorkerProcess($config);
        }
        $ret['tickWorker'][] = new TickProcess("Rpc.TickWorker", ['config' => $this->getConfig(), 'serviceList' => $this->list], false, 2, true);
        return $ret;
    }

    private function check()
    {
        if (empty($this->config->getServerIp())) {
            throw new Exception("serve ip is require");
        }
        if (empty($this->config->getNodeManager())) {
            throw new Exception("serve NodeManager require");
        }
    }

    function client(): RpcClient
    {
        return new RpcClient($this->getConfig()->getNodeManager());
    }
}