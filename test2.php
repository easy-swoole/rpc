<?php
require_once 'vendor/autoload.php';

use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\NodeManager\RedisManager;
use EasySwoole\Rpc\Response;
use EasySwoole\Rpc\ServiceNode;

$config = new Config();
$config->setNodeManager(new RedisManager());
$rpc = new Rpc($config);

go(function ()use($rpc){
    $node = new ServiceNode();
    $node->setServerIp('127.0.0.1');
    $node->setServerPort(9600);
    $client = $rpc->client();
    //因为nodeManager没有实现，因此默认指定节点测试
    $client->addCall('UserService','register',['arg1','arg2'])
        ->setOnFail(function (Response $response){
        var_dump($response->toArray());
    })
        ->setOnSuccess(function (Response $response){
            var_dump($response->toArray());
        })
        ->setServiceNode($node);
    $client->exec();
});




