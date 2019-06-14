<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/5/30
 * Time: 9:17
 */
require_once 'vendor/autoload.php';

use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Test\UserService;
use EasySwoole\Rpc\Test\OrderService;
use EasySwoole\Rpc\Test\NodeService;
use Swoole\Table;

$key = '__rpcRedis';

\EasySwoole\Component\TableManager::getInstance()->add($key, [
    'serviceName' => ['type' => Table::TYPE_STRING, 'size' => 32],
    'serviceVersion' => ['type' => Table::TYPE_STRING, 'size' => 8],
    'serverIp' => ['type' => Table::TYPE_STRING, 'size' => 15],
    'serverPort' => ['type' => Table::TYPE_INT, 'size' => 4],
    'nodeId' => ['type' => Table::TYPE_STRING, 'size' => 8],
    'lastHeartBeat' => ['type' => Table::TYPE_INT, 'size' => 4],
]);
$table = \EasySwoole\Component\TableManager::getInstance()->get($key);

$config = new Config();
$config->setServerIp('127.0.0.1');

$config->setNodeManager(new \EasySwoole\Rpc\NodeManager\TableManager($table));

$config->getBroadcastConfig()->setEnableBroadcast(true);
$config->getBroadcastConfig()->setEnableListen(true);
$config->getBroadcastConfig()->setSecretKey('zhongguo');
$rpc = new Rpc($config);
$rpc->add(new UserService());
$rpc->add(new OrderService());
$rpc->add(new NodeService());

$list = $rpc->generateProcess();
foreach ($list['worker'] as $p) {
    $p->getProcess()->start();
}

foreach ($list['tickWorker'] as $p) {
    $p->getProcess()->start();
}

while ($ret = \Swoole\Process::wait()) {
    echo "PID={$ret['pid']}\n";
}