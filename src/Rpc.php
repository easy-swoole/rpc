<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/27
 * Time: 下午5:51
 */

namespace EasySwoole\Rpc;




class Rpc
{

    private $config;
    private $client;
    private $nodeManager;
    private $actionList;
    function __construct(Config $config)
    {
        $this->config = $config;
        $this->nodeManager = new NodeManager($config->getMaxNodeNum());
        $this->actionList = new ActionList();
    }


    public function getActionList():ActionList
    {
        return $this->actionList;
    }

    /*
     * 注册一个tcp服务作为RPC通讯服务
     */
    function attach(\swoole_server $server,string $serviceName):void
    {
        if($this->config->isSubServerMode()){
            $subPort = $server->addListener($this->config->getListenAddress(),$this->config->getServicePort(),SWOOLE_TCP);
        }else{
            $subPort = $server;
        }
        /*
         * 配置包结构
         */
        $subPort->set(
            [
                'open_length_check' => true,
                'package_length_type'   => 'N',
                'package_length_offset' => 0,
                'package_body_offset'   => 4,
                'package_max_length'    => $this->config->getMaxPackage(),
                'heartbeat_idle_time' => $this->config->getHeartbeatIdleTime(),
                'heartbeat_check_interval' => $this->config->getHeartbeatCheckInterval()
            ]
        );
        //注册 onReceive 回调
        $subPort->on('receive',function (\swoole_server $server, int $fd, int $reactor_id, string $data){
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
        });
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