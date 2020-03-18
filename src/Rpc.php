<?php


namespace EasySwoole\Rpc;


use EasySwoole\Component\Process\Socket\TcpProcessConfig;
use EasySwoole\Component\Singleton;
use EasySwoole\Component\TableManager;
use EasySwoole\Pool\MagicPool;
use EasySwoole\Rpc\Exception\Exception;
use Swoole\Table;
use EasySwoole\Pool\Config as PoolConfig;
use EasySwoole\Component\Process\Config as ProcessConfig;

class Rpc
{
    protected $config;
    protected $list = [];
    protected $servicePool = [];

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
     * @return PoolConfig
     */
    public function add(AbstractService $service):?PoolConfig
    {
        if (!isset($this->list[$service->serviceName()])) {
            $config = new PoolConfig();
            $this->list[$service->serviceName()] = $service;
            $this->servicePool[$service->serviceName()] = new MagicPool(
                function () use ($service) {
                    return clone $service;
                },$config
            );
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
            return $config;
        }
        return null;
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
            $config->setProcessGroup('Rpc.Worker');
            $config->setListenAddress($this->getConfig()->getListenAddress());
            $config->setListenPort($this->getConfig()->getListenPort());
            $config->setArg(['config' => $this->getConfig(), 'serviceList' => $this->servicePool]);
            $ret['worker'][] = new WorkerProcess($config);
        }
        $tickConfig = new ProcessConfig();
        $tickConfig->setProcessGroup("Rpc.TickWorker");
        $tickConfig->setProcessName('Rpc.TickWorker.0');
        $tickConfig->setEnableCoroutine(true);
        $tickConfig->setArg(['config' => $this->getConfig(), 'serviceList' => $this->list]);
        $ret['tickWorker'][] = new TickProcess($tickConfig);
        return $ret;
    }

    private function check()
    {
        if (empty($this->config->getServerIp()) && $this->config->getBroadcastConfig()->isEnableBroadcast() == false) {
            throw new Exception("serve ip is require when you did not enable udp broadcast");
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