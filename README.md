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

## 设计代码阅读
### EasySwoole\Rpc
```
namespace EasySwoole\Rpc;



use EasySwoole\Component\Openssl;
use Swoole\Process;

class Rpc
{
    private $config;
    private $client;
    private $nodeManager;
    private $actionList;
    private $openssl;
    function __construct(Config $config)
    {
        //存储配置
        $this->config = $config;
        //根据配置项来实例化节点管理器
        $manager =  $config->getNodeManager();
        $this->nodeManager = new $manager;
        //实例化当前服务的行为容器
        $this->actionList = new ActionList();
        //判断是否启用openssl加密
        if(!empty($this->config->getAuthKey())){
            $this->openssl = new Openssl($this->config->getAuthKey());
        }
    }

    //获得当前RPC实例的行为容器
    public function getActionList():ActionList
    {
        return $this->actionList;
    }

    //当前RPC实例在收到TCP数据包的回调
    public function onRpcRequest(\swoole_server $server, int $fd, int $reactor_id, string $data):void
    {
        //对数据进行解包，解密
        $data = Pack::unpack($data);
        if($this->openssl){
            $data = $this->openssl->decrypt($data);
        }
        $json = json_decode($data,true);
        if(is_array($json)){
            $requestPackage = new RequestPackage($json);
            //对数据包进行过期判断和验签
            if(abs(time() - $requestPackage->getPackageTime()) < 2){
                if($requestPackage->getSignature() === $requestPackage->generateSignature($this->config->getAuthKey())){
                    $response = new Response();
                    $action = $requestPackage->getAction();
                    //获取行为回调
                    $callback = $this->actionList->__getAction($action);
                    if(!is_callable($callback)){
                        $callback = $this->config->getOnActionMiss();
                    }
                    try{
                        //执行行为回调
                        $ret = call_user_func($callback,$requestPackage,$response,$server,$fd);
                        if(!$ret instanceof Response){
                            $response->setMessage($ret);
                            $response->setStatus(Response::STATUS_OK);
                        }
                    }catch (\Throwable $throwable){
                        call_user_func($this->config->getOnException(), $throwable, $requestPackage,$response,$server ,$fd);
                    }
                    if($server->exist($fd)){
                        //响应给调用者
                        $msg = $response->__toString();
                        if($this->openssl){
                            $msg = $this->openssl->encrypt($msg);
                        }
                        $server->send($fd,Pack::pack($msg));
                    }
                }
            }
        }
        if($server->exist($fd)){
            $server->close($fd);
        }
    }

    public function onRpcBroadcast(\swoole_server $server, string $data, array $client_info)
    {
        if($this->openssl){
            $data = $this->openssl->decrypt($data);
        }
        $data = json_decode($data,true);
        if(is_array($data)){
            $requestPackage = new RequestPackage($data);
            if(abs(time() - $requestPackage->getPackageTime()) < 2){
                if($requestPackage->getSignature() === $requestPackage->generateSignature($this->config->getAuthKey())){
                    //忽略自己的广播
                    if($requestPackage->getNodeId() == $this->config->getNodeId()){
                        return;
                    }
                    if($requestPackage->getAction() == 'NODE_BROADCAST'){
                        $info = $requestPackage->getArg();
                        //若对方节点没有主动告知ip，则以网关ip为准
                        if(empty($info['serviceIp'])){
                            $info['serviceIp'] = $client_info['address'];
                        }
                        $serviceNode = new ServiceNode($info);
                        $this->nodeManager()->refreshServiceNode($serviceNode);
                    }else if(is_callable($this->config->getOnBroadcastReceive())){
                        call_user_func($this->config->getOnBroadcastReceive(),$server,$requestPackage,$client_info);
                    }
                }
            }
        }
    }

    public function getRpcBroadcastProcess(string $processName = 'RPC'):Process
    {
        return new Process(function (Process $process)use($processName){
            if(PHP_OS != 'Darwin'){
                $process->name($processName);
            }
            if (extension_loaded('pcntl')) {
                pcntl_async_signals(true);
            }
            Process::signal(SIGTERM,function ()use($process){
                //在节点关闭的时候，对外广播下线通知
                swoole_event_del($process->pipe);
                $process->exit(0);
            });
            swoole_event_add($process->pipe, function()use($process){
                $process->read(64 * 1024);
            });
            swoole_timer_tick($this->config->getBroadcastTTL()*1000,function (){
                $package = new RequestPackage();
                $package->setAction('NODE_BROADCAST');
                $package->setArg([
                    'nodeId'=>$this->config->getNodeId(),
                    'serviceName'=>$this->config->getServiceName(),
                    'serviceVersion'=>$this->config->getServiceVersion(),
                    'servicePort'=>$this->config->getListenPort(),
                    'serviceBroadcastPort'=>$this->config->getBroadcastListenPort(),
                    'nodeExpire'=>$this->config->getNodeExpire(),
                    'serviceIp'=>$this->config->getServiceIp(),
                ]);
                $this->broadcast($package);
                if(is_callable($this->config->getOnBroadcast())){
                    call_user_func($this->config->getOnBroadcast(),$this->config);
                }
            });
        });
    }

    function broadcast(RequestPackage $requestPackage)
    {
        $requestPackage->setPackageTime(time());
        $requestPackage->setNodeId($this->config->getNodeId());
        $requestPackage->generateSignature($this->config->getAuthKey());
        $msg = $requestPackage->__toString();
        if($this->openssl){
            $msg = $this->openssl->encrypt($msg);
        }
        foreach ($this->config->getBroadcastAddress() as $broadcastAddress){
            $broadcastAddress = explode(':',$broadcastAddress);
            if(($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
            {
                socket_set_option($sock,SOL_SOCKET,SO_BROADCAST,true);
                socket_sendto($sock,$msg,strlen($msg),0,$broadcastAddress[0],$broadcastAddress[1]);
                socket_close($sock);
            }
        }
    }

    function sendTo(string $msg,ServiceNode $serviceNode):?int
    {
        if(!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
        {
            return null;
        }
        $len = socket_sendto($sock, $msg , strlen($msg) , 0 , $serviceNode->getServiceIp() , $serviceNode->getServiceBroadcastPort());
        socket_close($sock);
        return $len;
    }

    /*
     * 每个进程中的client互相隔离
     */
    function client():Client
    {
        if(!$this->client){
            $this->client = new Client($this->config,$this->nodeManager);
        }
        return $this->client;
    }

    function nodeManager():NodeManager
    {
        return $this->nodeManager;
    }

}
```

## Composer安装
```
composer require easyswoole/rpc=2.x
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
$rpc->getActionList()->register('a1',function (RequestPackage $package, Response $response,\swoole_server $server,int $fd){
    var_dump($package->getArg());
    return 'AAA';
});

$rpc->getActionList()->register('a2',function (RequestPackage $package, Response $response,\swoole_server $server,int $fd){
    \co::sleep(0.2);
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
//cli下独立测试

$conf = new \EasySwoole\Rpc\Config();
$rpc = new \EasySwoole\Rpc\Rpc($conf);
$conf->setServiceName('serviceName');
//开启通讯密钥
//$conf->setAuthKey('123456');

//虚拟一个服务节点
$serviceNode = new \EasySwoole\Rpc\ServiceNode();
$serviceNode->setServiceName('serviceName');
$serviceNode->setServiceIp('127.0.0.1');
$serviceNode->setServicePort(9601);
$serviceNode->setNodeId('asadas');
//设置为永不过期
$serviceNode->setNodeExpire(0);
$rpc->nodeManager()->refreshServiceNode($serviceNode);

go(function ()use($rpc){
    $client = $rpc->client();
    $client->selectService('serviceName')->callAction('a1')->setArg(
        [
            'callTime'=>time()
        ]
    )->onSuccess(function (\EasySwoole\Rpc\Task $task,\EasySwoole\Rpc\Response $response,?\EasySwoole\Rpc\ServiceNode $serviceNode){
        var_dump('success'.$response->getMessage());
    })->onFail(function (\EasySwoole\Rpc\Task $task,\EasySwoole\Rpc\Response $response,?\EasySwoole\Rpc\ServiceNode $serviceNode){
        var_dump('fail'.$response->getStatus());
    })->setTimeout(1.5);

    $client->selectService('serviceName')->callAction('a2')->onSuccess(function (){
        var_dump('succ');
    });
    $client->call(1.5);
});
```