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
$rpc = new Rpc($config);
go(function () use ($rpc) {
    $client = $rpc->client();
    //调用那个服务
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

    $client->exec();
});