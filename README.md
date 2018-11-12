# EasySwoole RPC

## Composer
```
composer require easyswoole/rpc
``` 
## 示例代码

### 服务端
```php
$conf = new \EasySwoole\Rpc\Config();

$ser = new \swoole_http_server('0.0.0.0',9501);

$ser->on('request',function (){

});
$rpc = new \EasySwoole\Rpc\Rpc($conf);
$rpc->getActionList()->register('a1',function (\swoole_server $server,\EasySwoole\Rpc\Package $package,int $fd){
    \co::sleep(1);
    return 'asas';
});
try{
    $rpc->attach($ser,'test');
    $ser->start();
}catch (\Throwable $throwable){
    echo $throwable->getMessage();
}
``` 

### 客户端

#### EasySwoole 封装实现
```php
$conf = new \EasySwoole\Rpc\Config();
$rpc = new \EasySwoole\Rpc\Rpc($conf);

go(function ()use($rpc){
    $client = $rpc->client();
    $client->selectService('service',function (){
        $node = new \EasySwoole\Rpc\ServiceNode();
        $node->setIp('127.0.0.1');
        $node->setPort(9601);
        return $node;
    })->setAction('a1')->setArg(
        [
            'callTime'=>time()
        ]
    )->onSuccess(function (\EasySwoole\Rpc\Task $task,\EasySwoole\Rpc\Response $response,?\EasySwoole\Rpc\ServiceNode $serviceNode){
        var_dump('success');
    })->onFail(function (\EasySwoole\Rpc\Task $task,\EasySwoole\Rpc\Response $response,?\EasySwoole\Rpc\ServiceNode $serviceNode){
        var_dump('fail');
    })->setTimeout(1.5);
    $client->call(1.5);

});
```