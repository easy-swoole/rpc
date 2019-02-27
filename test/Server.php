<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/2/27
 * Time: 10:00
 */
include_once dirname(__DIR__) . "/vendor/autoload.php";

use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Request;
use EasySwoole\Rpc\Response;

$config = new Config();
//注册服务名称
$config->setServiceName('ser1');
//设置服务的Ip(ps:集群)
//$config->setServiceIp('127.0.0.1');
//设置版本
//$config->setServiceVersion('1.0.1');
//设置广播地址，可以多个地址
//$config->getAutoFindConfig()->setAutoFindBroadcastAddress(['127.0.0.1:9600']);
//设置广播监听地址
//$config->getAutoFindConfig()->setAutoFindListenAddress('127.0.0.1:9600');
//设置广播秘钥
//$config->getAutoFindConfig()->setEncryptKey('123456abcd');
//设置节点管理器
//$config->setNodeManager(\EasySwoole\Rpc\NodeManager\RedisManager::class);
//设置接收数据格式
//$config->setSerializeType($config::SERIALIZE_TYPE_JSON);

$rpc = new Rpc($config);
//注册方法
$rpc->registerAction('call1', function (Request $request, Response $response) {
    //获取请求参数
    var_dump($request->getArg());
    //设置返回给客户端信息
    $response->setMessage('response');
});
$rpc->registerAction('call2');

$autoFindProcess = $rpc->autoFindProcess();

$http = new swoole_http_server("127.0.0.1", 9525);
//添加自定义进程（监听和广播）
$http->addProcess($autoFindProcess->getProcess());
//rpc作为一个子服务运行
$sub = $http->addlistener("127.0.0.1", 9526, SWOOLE_TCP);

$rpc->attachToServer($sub);

$http->on("request", function ($request, $response) {
    $response->end("Hello World\n");
});
$http->start();

//rpc 作为主服务运行
//$tcp = new swoole_server('127.0.0.1', 9526);
//$tcp->addProcess($autoFindProcess->getProcess());
//$rpc->attachToServer($tcp);
//$tcp->start();