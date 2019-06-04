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
use EasySwoole\Rpc\NodeManager\RedisManager;
use EasySwoole\Rpc\Test\OrderService1;


$config = new Config();
$config->setServerIp('127.0.0.1');
$config->setNodeManager(new \EasySwoole\Rpc\NodeManager\TableManager());
$rpc = new Rpc($config);
//$rpc->add(new UserService());
$rpc->add(new OrderService1());

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