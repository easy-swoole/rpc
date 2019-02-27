# EasySwoole RPC
很多传统的Phper并不懂RPC是什么，RPC全称Remote Procedure Call，中文译为远程过程调用,其实你可以把它理解为是一种架构性上的设计，或者是一种解决方案。
例如在某庞大商场系统中，你可以把整个商场拆分为N个微服务（理解为N个独立的小模块也行），例如：
    
- 订单系统
- 用户管理系统
- 商品管理系统
- 等等 

那么在这样的架构中，就会存在一个Api网关的概念，或者是叫服务集成者。我的Api网关的职责，就是把一个请求
，拆分成N个小请求，分发到各个小服务里面，再整合各个小服务的结果，返回给用户。例如在某次下单请求中，那么大概
发送的逻辑如下：
- Api网关接受请求
- Api网关提取用户参数，请求用户管理系统，获取用户余额等信息，等待结果
- Api网关提取商品参数，请求商品管理系统，获取商品剩余库存和价格等信息，等待结果。
- Api网关融合用户管理系统、商品管理系统的返回结果，进行下一步调用（假设满足购买条件）
- Api网关调用用户管理信息系统进行扣款，调用商品管理系统进行库存扣减，调用订单系统进行下单（事务逻辑和撤回可以用请求id保证，或者自己实现其他逻辑调度）
- APi网关返回综合信息给用户

而在以上发生的行为，就称为远程过程调用。而调用过程实现的通讯协议可以有很多，比如常见的HTTP协议。而EasySwoole RPC采用自定义短链接的TCP协议实现，每个请求包，都是一个JSON，从而方便实现跨平台调用。

什么是服务熔断？
 
粗暴来理解，一般是某个服务故障或者是异常引起的，类似现实世界中的‘保险丝’，当某个异常条件被触发，直接熔断整个服务，而不是一直等到此服务超时。

什么是服务降级?

粗暴来理解，一般是从整体负荷考虑，就是当某个服务熔断之后，服务器将不再被调用，此时客户端可以自己准备一个本地的fallback回掉，返回一个缺省值，这样做，虽然服务水平下降，但好歹，比直接挂掉要强。
服务降级处理是在客户端实现完成的，与服务端没有关系。

什么是服务限流？

粗暴来理解，例如某个服务器最多同时仅能处理100个请求，或者是cpu负载达到百分之80的时候，为了保护服务的稳定性，则不在希望继续收到
新的连接。那么此时就要求客户端不再对其发起请求。因此EasySwoole RPC提供了NodeManager接口，你可以以任何的形式来
监控你的服务提供者，在getServiceNode方法中，返回对应的服务器节点信息即可。  



## 下面是独立运行的例子
### Server
```
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Request;
use EasySwoole\Rpc\Response;
$config = new Config();
//注册服务名称
$config->setServiceName('ser1');
$config->get
$rpc = new Rpc($config);

$rpc->registerAction('call1',function (Request $request,Response $response){
    $response->setMessage('response');
});
$rpc->registerAction('call2',function (Request $request,Response $response){
    $response->setMessage(['response2222222']);
});

$autoFindProcess = $rpc->autoFindProcess();
$http = new swoole_http_server("127.0.0.1", 9501);
$http->addProcess($autoFindProcess->getProcess());
$sub = $http->addlistener("127.0.0.1", 9502,SWOOLE_TCP);
$rpc->attachToServer($sub);
$http->on("request", function ($request, $response) {
    $response->end("Hello World\n");
    
});

$http->start();

rpc 作为主服务运行
$tcp = new swoole_server('127.0.0.1', 9526);
$tcp->addProcess($autoFindProcess->getProcess());
$rpc->attachToServer($tcp);
$tcp->start();

```

### EasySwoole\Rpc\Client
```
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Response;
$config = new Config();
$rpc = new Rpc($config);
go(function ()use($rpc){
    $client = $rpc->client();
    $serviceClient = $client->selectService('ser1');
    $serviceClient->createTask()->setAction('call1')->setArg([
        'arg'=>1
    ])->setOnSuccess(function (Response $response){
        var_dump($response->getMessage());
    })->setOnFail(function (){

    });

    $serviceClient->createTask()->setAction('call2');

    $client->exec();
});
```

## Composer安装
```
composer require easyswoole/rpc=3.x
``` 


### 原生PHP
```
//以下例子为未启用数据openssl加密

$authKey  = null; //RPC鉴权秘钥，默认null

$data = [
    'nodeId'=>'xxx',//节点id，如果没有做节点过滤，那么随意构造
    'packageId'=>'xxxxx',//包Id,随意构造
    'action'=>'a1',//行为名称
    'packageTime'=>time(),//包请求时间
    'arg'=>[
        'args1'=>'args1',
        'args2'=>'args2'
    ]
];

$data['signature'] = md5($data['packageId'].$authKey.$data['packageTime'].implode('',$data['arg']));

$raw = json_encode($data);
//如果启用了openssl ，请在此处对$raw 加密 ，加密方法为 DES-EDE3


$fp = stream_socket_client('tcp://127.0.0.1:9601');
fwrite($fp,pack('N', strlen($raw)).$raw);

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

## NodeJs 
```
var net = require('net');
var pack = require('php-pack').pack;
var unpack = require('php-pack').unpack;
var md5 = require("md5");

var authKey = '';

var json = {
    'nodeId':'xxx',//节点id，如果没有做节点过滤，那么随意构造
    'packageId':'xxxxx',//包Id,随意构造
    'action':'a1',//行为名称
    'packageTime':'',//包请求时间
    'arg':{
        'argKey1':'arg1',
        'argKey2':'arg2'
    },
    'signature':'xxx'//包签名
};


json.packageTime = parseInt(Date.now()/1000);

var argString = '';

for(var key in json.arg){
    argString += json.arg[key];
}

console.log(json.packageId + authKey + json.packageTime + argString);


json.signature = md5(json.packageId + authKey + json.packageTime + argString);

console.log(json.signature);

var send = JSON.stringify(json);
//
send = Buffer.concat([pack("N",send.length), Buffer.from(send)]);

var client = new net.Socket();
client.connect(9601, '127.0.0.1', function() {
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
    client.destroy()
});
client.on('error',function (error) {
    console.log(error);
    client.destroy()
});
```