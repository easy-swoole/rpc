<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/5/30
 * Time: 9:19
 */
require_once 'vendor/autoload.php';

use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\NodeManager\RedisManager;
use EasySwoole\Rpc\Response;

$config = new Config();
$nodeManager = new RedisManager('127.0.0.1');
$config->setNodeManager($nodeManager);
$rpc = new Rpc($config);

\Swoole\Coroutine::create(function () use ($rpc) {
    $client = $rpc->client();
    $client->addCall('UserService', 'register', ['arg1', 'arg2'])
        ->setOnFail(function (Response $response) {
            print_r($response->toArray());
        })
        ->setOnSuccess(function (Response $response) {
            print_r($response->toArray());
        });

    $client->exec();
});