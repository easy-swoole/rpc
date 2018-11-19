# EasySwoole RPC

## Composer
```
composer require easyswoole/rpc
``` 
## 示例代码

### 服务端
```php

use EasySwoole\Rpc\RequestPackage;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Response;
$conf = new Config();
$conf->setServiceName('serviceName');
$conf->setBroadcastTTL(4);
//开启通讯密钥
//$conf->setAuthKey('123456');


//创建主服务
$ser = new \swoole_http_server('0.0.0.0',9501);

$ser->on('request',function ($request,$response){
    $response->write('hello world');
    $response->end();
});

$rpc = new \EasySwoole\Rpc\Rpc($conf);

//注册action
$rpc->getActionList()->register('a1',function (\swoole_server $server, RequestPackage $package, Response $response,int $fd){
    var_dump($package->getArg());
    return 'AAA';
});

$rpc->getActionList()->register('a2',function (\swoole_server $server, RequestPackage $package, Response $response,int $fd){
    return 'a2';
});


//注册广播进程，主动对外udp广播服务节点信息
$ser->addProcess($rpc->getRpcBroadcastProcess());

//创建一个udp子服务，用来接收udp广播

$udp = $ser->addListener($conf->getBroadcastListenAddress(),$conf->getBroadcastListenPort(),SWOOLE_UDP);
$udp->on('packet',function (\swoole_server $server, string $data, array $client_info)use($rpc){
    $rpc->onRpcBroadcast($server,$data,$client_info);
});

//创建一个tcp子服务，用来接收rpc的tcp请求。
$sub = $ser->addListener($conf->getListenAddress(),$conf->getListenPort(),SWOOLE_TCP);
$sub->set($conf->getProtocolSetting());
$sub->on('receive',function (\swoole_server $server, int $fd, int $reactor_id, string $data)use($rpc){
    $rpc->onRpcRequest( $server,  $fd,  $reactor_id,  $data);
});

$ser->start();
``` 

### 客户端

#### EasySwoole 封装实现
```php
$conf = new \EasySwoole\Rpc\Config();
$rpc = new \EasySwoole\Rpc\Rpc($conf);
$conf->setServiceName('serviceName');
//开启通讯密钥
//$conf->setAuthKey('123456');
go(function ()use($rpc){
    $client = $rpc->client();
    $client->selectService('service')->callAction('a1')->setArg(
        [
            'callTime'=>time()
        ]
    )->onSuccess(function (\EasySwoole\Rpc\Task $task,\EasySwoole\Rpc\Response $response,?\EasySwoole\Rpc\ServiceNode $serviceNode){
        var_dump('success'.$response->getMessage());
    })->onFail(function (\EasySwoole\Rpc\Task $task,\EasySwoole\Rpc\Response $response,?\EasySwoole\Rpc\ServiceNode $serviceNode){
        var_dump('fail'.$response->getMessage());
    })->setTimeout(1.5);

    $client->selectService('service')->callAction('a2')->onSuccess(function (){
        var_dump('succ');
    });

    $client->call(1.5);

});
```