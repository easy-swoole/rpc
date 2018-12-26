<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/27
 * Time: 下午5:51
 */

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
        $this->config = $config;
        $manager =  $config->getNodeManager();
        $this->nodeManager = new $manager;
        $this->actionList = new ActionList();
        if(!empty($this->config->getAuthKey()) && $config->isEnableOpenssl()){
            $this->openssl = new Openssl($this->config->getAuthKey());
        }
    }


    public function getActionList():ActionList
    {
        return $this->actionList;
    }


    public function onRpcRequest(\swoole_server $server, int $fd, int $reactor_id, string $data):void
    {
        $data = Pack::unpack($data);
        if($this->openssl){
            $data = $this->openssl->decrypt($data);
        }
        $json = json_decode($data,true);
        if(is_array($json)){
            $requestPackage = new RequestPackage($json);
            if(abs(time() - $requestPackage->getPackageTime()) < 2){
                if($requestPackage->getSignature() === $requestPackage->generateSignature($this->config->getAuthKey())){
                    $response = new Response();
                    //如果返回false  那么拦截请求
                    if(is_callable($this->config->getOnRequest())){
                       if(call_user_func($this->config->getOnRequest(),$requestPackage,$response,$this->config,$server,$fd) === false){
                           if($server->exist($fd)){
                               $server->close($fd);
                           }
                           return;
                       };
                    }
                    $action = $requestPackage->getAction();
                    $callback = $this->actionList->__getAction($action);
                    if(!is_callable($callback)){
                        $callback = $this->config->getOnActionMiss();
                    }
                    try{
                        $ret = call_user_func($callback, $requestPackage,$response,$server,$fd);
                        if(!$ret instanceof Response){
                            $response->setMessage($ret);
                            $response->setStatus(Response::STATUS_OK);
                        }
                    }catch (\Throwable $throwable){
                        call_user_func($this->config->getOnException(),$throwable, $requestPackage,$response,$server,$fd);
                    }
                    if(is_callable($this->config->getAfterRequest())){
                        call_user_func($this->config->getAfterRequest(),$requestPackage,$response,$this->config,$server,$fd);
                    }
                    if($server->exist($fd)){
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
                    }else if($requestPackage->getAction() == 'NODE_OFFLINE'){
                        $info = $requestPackage->getArg();
                        //若对方节点没有主动告知ip，则以网关ip为准
                        if(empty($info['serviceIp'])){
                            $info['serviceIp'] = $client_info['address'];
                        }
                        $serviceNode = new ServiceNode($info);
                        $this->nodeManager()->offlineServiceNode($serviceNode);
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
                $package = new RequestPackage();
                $package->setAction('NODE_OFFLINE');
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
                if(is_callable($this->config->getOnShutdown())){
                    call_user_func($this->config->getOnShutdown(),$this->config);
                }
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

    function nodeManager():NodeManagerInterface
    {
        return $this->nodeManager;
    }

}