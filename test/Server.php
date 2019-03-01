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
$config->setServiceName('ser111');
//设置广播地址，可以多个地址
$config->getAutoFindConfig()->setAutoFindBroadcastAddress(['127.0.0.1:9600']);
//设置广播监听地址
$config->getAutoFindConfig()->setAutoFindListenAddress('127.0.0.1:9600');

$rpc = new Rpc($config);
//注册方法
$rpc->registerAction('call1', function (Request $request, Response $response) {
    //获取请求参数
    var_dump($request->getArg());
    //设置返回给客户端信息
    $response->setMessage('response');
});
$rpc->registerAction('call2', function (Request $request, Response $response) {
});
$autoFindProcess = $rpc->autoFindProcess('es_rpc_process_1');

$config2=new Config();

$config2->setServiceName('ser222');

$rpc2 = new Rpc($config2);

$autoFindProcess2 = $rpc2->autoFindProcess('es_rpc_process_2');

$http = new swoole_http_server("127.0.0.1", 9525);

//添加自定义进程（监听和广播）
$http->addProcess($autoFindProcess->getProcess());

$http->addProcess($autoFindProcess2->getProcess());
//rpc作为一个子服务运行
$sub = $http->addlistener("127.0.0.1", 9526, SWOOLE_TCP);

$sub2 = $http->addlistener("127.0.0.1", 9527, SWOOLE_TCP);

$rpc->attachToServer($sub);

$rpc2->attachToServer($sub2);

/**
 * http请求回调
 */
$http->on("request", function ($request, $response) {
    $response->end("Hello World\n");
});
$http->start();


////rpc 作为主服务运行
//$tcp = new swoole_server('127.0.0.1', 9526);
//$tcp->addProcess($autoFindProcess->getProcess());
//$rpc->attachToServer($tcp);

//$tcp->start();