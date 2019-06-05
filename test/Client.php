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
use EasySwoole\Rpc\ServiceNode;

$config = new Config();
$table=new Swoole\Table(1024);
$nodeManager=new \EasySwoole\Rpc\NodeManager\TableManager($table);

//$nodeManager = new RedisManager('127.0.0.1', 6379);
//go(function () use ($nodeManager) {
//    $list = $nodeManager->getServiceNodes('NodeService');
//    print_r($list);
//});
//go(function () use ($nodeManager) {
//    $serviceNode = $nodeManager->getServiceNode('UserService');
//    if (!empty($serviceNode)) {
//        print_r($serviceNode->toArray());
//    }
//});
//return;

$config->setNodeManager($nodeManager);
$rpc = new Rpc($config);

go(function () use ($rpc) {
    $node = new ServiceNode();
    $node->setServerIp('127.0.0.1');
    $node->setServerPort(9600);
    $client = $rpc->client();
    //因为nodeManager没有实现，因此默认指定节点测试
//    $client->addCall('UserService', 'register', ['arg1', 'arg2'])
//        ->setOnFail(function (Response $response) {
//            var_dump($response->toArray());
//        })
//        ->setOnSuccess(function (Response $response) {
//            var_dump($response->toArray());
//        })
//        ->setServiceNode($node);


    $client->addCall('NodeService', 'getServiceNodes',['serviceName'=>'OrderService'])
        ->setOnFail(function (Response $response) {
            print_r($response->toArray());
        })
        ->setOnSuccess(function (Response $response) {
            print_r($response->toArray());
        })
        ->setServiceNode($node);


//    $client->addCall('NodeService', 'getServiceNode',['serviceName'=>'OrderService'])
//        ->setOnFail(function (Response $response) {
//            print_r($response->toArray());
//        })
//        ->setOnSuccess(function (Response $response) {
//            print_r($response->toArray());
//        })
//        ->setServiceNode($node);

    $client->addCall('NodeService', 'deleteServiceNode', ['serviceName' => 'OrderService'])
        ->setOnFail(function (Response $response) {
            print_r($response->toArray());
        })
        ->setOnSuccess(function (Response $response) {
            print_r($response->toArray());
        })
        ->setServiceNode($node);

    $client->exec();
});