<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/27
 * Time: 下午5:51
 */

namespace EasySwoole\Rpc;



use Swoole\Process;

class Rpc
{

    private $config;
    private $client;
    private $nodeManager;
    private $actionList;
    private $broadcast;
    function __construct(Config $config)
    {
        $this->config = $config;
        $manager =  $config->getNodeManager();
        $this->nodeManager = new $manager;
        $this->actionList = new ActionList();
        $this->broadcast = new Broadcast($config);
    }


    public function getActionList():ActionList
    {
        return $this->actionList;
    }


    public function onRpcRequest(\swoole_server $server, int $fd, int $reactor_id, string $data):void
    {
        $json = json_decode(Pack::unpack($data),true);
        if(is_array($json)){
            $requestPackage = new RequestPackage($json);
            if(abs(time() - $requestPackage->getPackageTime()) < 2){
                if($requestPackage->getSignature() === $requestPackage->generateSignature($this->config->getAuthKey())){
                    $response = new Response();
                    $action = $requestPackage->getAction();
                    $callback = $this->actionList->__getAction($action);
                    if(!is_callable($callback)){
                        $callback = $this->config->getOnActionMiss();
                    }
                    try{
                        $ret = call_user_func($callback, $server,$requestPackage,$response,$fd);
                        if(!$ret instanceof Response){
                            $response = new Response([
                                'message'=>$ret,
                                'status'=>Response::STATUS_OK
                            ]);
                        }
                    }catch (\Throwable $throwable){
                        call_user_func($this->config->getOnException(), $throwable, $server ,$fd, $requestPackage,$response);
                    }
                    if($server->exist($fd)){
                        $server->send($fd,Pack::pack((string)$response));
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
        $data = json_decode($data,true);
        if(is_array($data)){
            $requestPackage = new RequestPackage($data);
            if(abs(time() - $requestPackage->getPackageTime()) < 2){
                if($requestPackage->getSignature() === $requestPackage->generateSignature($this->config->getAuthKey())){

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
            swoole_timer_tick($this->config->getBroadcastTTL(),function (){

            });
        });
    }

    function broadcast(RequestPackage $requestPackage)
    {

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