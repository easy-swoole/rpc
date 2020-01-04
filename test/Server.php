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
use EasySwoole\Rpc\NodeManager\RedisManager;
use EasySwoole\Rpc\Test\UserService;
use EasySwoole\Rpc\Test\OrderService;
use EasySwoole\Rpc\Test\NodeService;

$config = new Config();
$config->setServerIp('127.0.0.1');

$pool=new \EasySwoole\RedisPool\RedisPool(new \EasySwoole\Redis\Config\RedisConfig());

$config->setNodeManager(new RedisManager($pool));

//$config->getBroadcastConfig()->setEnableBroadcast(true);
//$config->getBroadcastConfig()->setEnableListen(true);
//$config->getBroadcastConfig()->setSecretKey('zhongguo');

$rpc = new Rpc($config);
$rpc->add(new UserService())->setMinObjectNum(10)->setMaxObjectNum(20);
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



