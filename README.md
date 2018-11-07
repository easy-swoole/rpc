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
$ser->set([
    'worker_num'=>1
]);
$ser->on('request',function (){

});
$rpc = new \EasySwoole\Rpc\Rpc($conf);
try{

    $rpc->attach('test',$ser);
    $ser->start();
}catch (\Throwable $throwable){
    echo $throwable->getMessage();
}
``` 

### 客户端

#### EasySwoole 封装实现
```php

$chan = new \Swoole\Coroutine\Channel(2);

$package = new \EasySwoole\Rpc\Package();
$package->setPackageTime(time());
$package->generateSignature();

go(function ()use($chan,$package){
    $client = new Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
    $client->connect('127.0.0.1',9601);
    $client->send(\EasySwoole\Rpc\Pack::pack($package->__toString()));
    $ret = \EasySwoole\Rpc\Pack::unpack($client->recv(2));
    $chan->push(['1'=>$ret]);
});

go(function ()use($chan,$package){
    $client = new Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
    $client->connect('127.0.0.1',9601);
    $client->send(\EasySwoole\Rpc\Pack::pack($package->__toString()));
    $ret = \EasySwoole\Rpc\Pack::unpack($client->recv(2));
    $chan->push(['2'=>$ret]);
});

go(function ()use($chan){
    $result = [];
    for ($i = 0; $i < 2; $i++)
    {
        $result += $chan->pop();
    }
    var_dump($result);
});
```