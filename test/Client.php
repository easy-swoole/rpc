<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/2/27
 * Time: 10:03
 */
include_once dirname(__DIR__) . "/vendor/autoload.php";

use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Response;

$config = new Config();
//$config->setNodeManager(\EasySwoole\Rpc\NodeManager\TableManager::class);
$rpc = new Rpc($config);
//获取所有服务列表
$nodeList = $config->getNodeManager()->allServiceNodes();
print_r($nodeList);exit;
go(function () use ($rpc) {
    $client = $rpc->client();
    //调用服务
    $serviceClient = $client->selectService('ser1');
    //创建
    $serviceClient->createTask()->setAction('call1')->setArg(['arg' => 1])
        ->setOnSuccess(function (Response $response) {
            var_dump($response->getMessage());
        })->setOnFail(function () {

        });

    $serviceClient->createTask()->setAction('call3')
        ->setOnSuccess(function (Response $response) {
            var_dump($response->getMessage());
        });
    $serviceClient2 = $client->selectService('ser2');
    $serviceClient2->createTask()->setAction('call1')
        ->setOnSuccess(function (Response $response) {
            var_dump($response->getMessage());
        });
    $client->exec();
});