# EasySwoole RPC
这是一个基于Swoole Server独立实现的RPC服务。
 - 协程调度
 - 服务自动发现
 - 服务熔断
 - 服务降级
 - Openssl加密
 - Ip白名单
 - 跨平台，跨语言支持
## Composer
```
composer require easyswoole/rpc
``` 
## 示例代码

### 服务端
```php

//实现一个Rpc控制器
class Rpc extends \EasySwoole\Rpc\AbstractInterface\AbstractService
{
    function test()
    {
        $this->getResponse()->setResult(time());
        var_dump($this->getCaller()->getArgs());
    }

    function test2()
    {
        $this->getResponse()->setStatus(\EasySwoole\Rpc\Bean\Response::STATUS_RESPONSE_DETACH);
    }
}
//实现一个Rpc控制器
class Rpc2 extends \EasySwoole\Rpc\AbstractInterface\AbstractService
{
    function fuck()
    {
        $this->getResponse()->setMessage('this is Rpc2');
    }

    function actionNotFound(?string $action)
    {
        $this->getResponse()->setMessage("{$action} action miss");
    }
}

//实例化一个配置项
$conf = new \EasySwoole\Rpc\Config();
$conf->setSubServerMode(false);//设置为主服务模式
$conf->setEnableBroadcast(true);//开启服务自动广播，可以修改广播地址，实现定向ip组广播
$conf->setIpWhiteList()->set(['127.0.0.1','192.168.0.216']);//设置数据包白名单，默认允许127.0.0.1的

$ser = new \swoole_server('0.0.0.0',9501);
try{
    \EasySwoole\Rpc\Rpc::getInstance()->setConfig($conf);
    \EasySwoole\Rpc\Rpc::getInstance()->registerService('a',Rpc::class);
    \EasySwoole\Rpc\Rpc::getInstance()->registerService('b',Rpc2::class);
    \EasySwoole\Rpc\Rpc::getInstance()->attach($ser);
    $ser->start();
}catch (\Throwable $throwable){
    echo $throwable->getMessage();
}

``` 

### 客户端

#### EasySwoole 封装实现
```php
//这里的go()是cli单元测试的时候，创建携程环境，在swoole4的onRequest，onReceive等携程环境中，不需要手动创建
go(function (){
    try{
        //注册Rpc配置，并取得一个客户端实例
        $client = \EasySwoole\Rpc\Rpc::getInstance()->setConfig(new \EasySwoole\Rpc\Config())
            ->client();
        //模拟在未开启服务广播发现的时候，注册已知节点，服务自主发现需要借助swoole server实现。
        $node = new \EasySwoole\Rpc\Bean\ServiceNode();
        $node->setIp('127.0.0.1');
        $node->setPort(9501);
        $node->setLastHeartBeat(time());
        $node->setServiceId('aaaaa');
        $node->setServiceName('a');
        \EasySwoole\Rpc\Rpc::getInstance()->refreshServiceNode($node);

        $node = new \EasySwoole\Rpc\Bean\ServiceNode();
        $node->setIp('127.0.0.1');
        $node->setPort(9501);
        $node->setLastHeartBeat(time());
        $node->setServiceId('bbbbb');
        $node->setServiceName('b');
        \EasySwoole\Rpc\Rpc::getInstance()->refreshServiceNode($node);

        //添加服务调用
        $client->addCall('a','test')
            ->success(function (\EasySwoole\Rpc\Bean\Response $response){
                var_dump('success');
            })->fail(function (){
                var_dump('fail');
            });
            
        $client->addCall('a','test2')
            ->success(function (){
                var_dump('success2');
            })->fail(function (\EasySwoole\Rpc\Bean\Response $response){
                var_dump('fail2 '.$response->getStatus());
            });

        $client->addCall('b','fuck')
            ->success(function (\EasySwoole\Rpc\Bean\Response $response){
                var_dump('success2 '.$response->getMessage());
            })->fail(function (\EasySwoole\Rpc\Bean\Response $response){
                var_dump('fail2 '.$response->getStatus());
            });

        $client->addCall('b','none')
            ->success(function (\EasySwoole\Rpc\Bean\Response $response){
                var_dump('success2 '.$response->getMessage());
            })->fail(function (\EasySwoole\Rpc\Bean\Response $response){
                var_dump('fail2 '.$response->getMessage());
            });

        $t = microtime(true);
        //默认超时0.5s
        $client->exec(0.1);
        var_dump(round(microtime(true) - $t,3));
    }catch (\Throwable $throwable){
        echo $throwable;
    }
});
```

#### 原生PHP
RPC 数据包格式为JSON，字段如下
```json
{
    "service": "a", 
    "action": "test", 
    "args": [ ]
}
```
调用代码
```php

$arr = [
    'service'=>'a',
    'action'=>'test',
    'args'=>[
        'a'=>1
    ]
];

$fp = stream_socket_client('tcp://127.0.0.1:9501');

echo $sendStr = json_encode($arr);

$data = pack('N', strlen($sendStr)).$sendStr;

fwrite($fp,$data);

$data = fread($fp,65533);
//做长度头部校验
$len = unpack('N',$data);
$data = substr($data,'4');
if(strlen($data) != $len[1]){
    echo 'data error';
}else{
    $json = json_decode($data,true);
    //这就是服务端返回的结果，
    var_dump($json);
}
fclose($fp);
```

#### 原生NodeJs
```js
var net = require('net');
var pack = require('php-pack').pack;
var unpack = require('php-pack').unpack;
var json = {
    service:'a',
    action:'test',
    args:[]
};

var send = JSON.stringify(json);

send = Buffer.concat([pack("N",send.length), Buffer.from(send)]);

var client = new net.Socket();
client.connect(9501, '127.0.0.1', function() {
    console.log('Connected');
    client.write(send);

});

client.on('data', function(data) {
    console.log('Received: ' + data);
    var ret = JSON.parse(data.toString().substr(4));
    console.log('status: ' +  ret.status);
    client.destroy()
});

client.on('close', function() {
    console.log('Connection closed');
});
client.on('error',function (error) {
    console.log(error);
});

```