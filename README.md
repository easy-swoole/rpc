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

$ser = new \swoole_http_server('0.0.0.0',9501);

$ser->on('request',function ($request,$response){
    $response->write('hello world');
    $response->end();
});
$rpc = new \EasySwoole\Rpc\Rpc($conf);
$ser->addProcess($rpc->getRpcBroadcastProcess());
$rpc->getActionList()->register('a1',function (\swoole_server $server, RequestPackage $package, Response $response,int $fd){
    return 'asas';
});
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

go(function ()use($rpc){
    $client = $rpc->client();
    $client->selectService('service')->setAction('a1')->setArg(
        [
            'callTime'=>time()
        ]
    )->onSuccess(function (\EasySwoole\Rpc\Task $task,\EasySwoole\Rpc\Response $response,?\EasySwoole\Rpc\ServiceNode $serviceNode){
        var_dump('success'.$response->getMessage());
    })->onFail(function (\EasySwoole\Rpc\Task $task,\EasySwoole\Rpc\Response $response,?\EasySwoole\Rpc\ServiceNode $serviceNode){
        var_dump('fail'.$response->getMessage());
    })->setTimeout(1.5);
    $client->call(1.5);

});
```