<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/6/14
 * Time: 16:42
 */
require_once 'vendor/autoload.php';

use EasySwoole\Rpc\Response;
use EasySwoole\Rpc\Config;
$config = new Config();
$config->setServerIp('127.0.0.1');
$table=new Swoole\Table(1024);
//跨进程了，这边设置节点暂无使用，因为接口必须要继承，
$config->setNodeManager(new \EasySwoole\Rpc\NodeManager\TableManager($table));

$rpc = new \EasySwoole\Rpc\Rpc($config);
\Swoole\Coroutine::create(function () use ($rpc) {
    //因为节点管理为table,这边暂无法使用节点管理器
    $node = new \EasySwoole\Rpc\ServiceNode();
    $node->setServerIp('127.0.0.1');
    $node->setServerPort(9600);
    $client = $rpc->client();

    $client->addCall('NodeService', 'getServiceNodes', ['serviceName' => 'UserService'])
        ->setOnFail(function (Response $response) {
            print_r($response->toArray());
        })
        ->setOnSuccess(function (Response $response) {
            print_r($response->toArray());
        })
        ->setServiceNode($node);
    $client->addCall('NodeService', 'getServiceNode', ['serviceName' => 'OrderService'])
        ->setOnFail(function (Response $response) {
            print_r($response->toArray());
        })
        ->setOnSuccess(function (Response $response) {
            print_r($response->toArray());
        })
        ->setServiceNode($node);

//    $client->addCall('NodeService', 'deleteServiceNode', ['serviceName' => 'OrderService'])
//        ->setOnFail(function (Response $response) {
//            print_r($response->toArray());
//        })
//        ->setOnSuccess(function (Response $response) {
//            print_r($response->toArray());
//        });

    $client->exec();
});