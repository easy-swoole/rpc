<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/27
 * Time: 下午5:51
 */

namespace EasySwoole\Rpc;


use EasySwoole\Component\Openssl;
use EasySwoole\Component\Singleton;
use EasySwoole\Rpc\AbstractInterface\AbstractService;
use EasySwoole\Rpc\Bean\Caller;
use EasySwoole\Rpc\Bean\IpWhiteList;
use EasySwoole\Rpc\Bean\Response;
use EasySwoole\Rpc\Bean\ServiceNode;
use EasySwoole\Rpc\Client\Client;
use EasySwoole\Trigger\Trigger;
use Swoole\Timer;
use EasySwoole\Rpc\Bean\Client as ClientInfo;

class Rpc
{
    use Singleton;
    private $serviceList = [];
    private $openssl = null;
    private $swooleTable;
    private $config;
    private $serverPort = null;

    /*
     * 注册一个tcp服务作为RPC通讯服务
     */
    function attach(\swoole_server $server)
    {
        if(!$this->config instanceof Config){
            throw new \Exception('Rpc Config is require');
        }
        if($this->config->isSubServerMode()){
            $subPort = $server->addListener($this->config->getListenHost(),$this->config->getServicePort(),SWOOLE_TCP);
            $this->serverPort = $this->config->getServicePort();
        }else{
            $this->serverPort = $server->port;
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
        //是否启用数据包加密
        if(!empty($this->config->getSecretKey())){
            $this->openssl = new Openssl($this->config->getSecretKey());
        }
        //注册 onReceive 回调
        $subPort->on('receive',function (\swoole_server $server, int $fd, int $reactor_id, string $data){
            $info = $server->connection_info($fd);
            //这里做ip白名单过滤
            if($this->config->getIpWhiteList() instanceof IpWhiteList){
                if(!$this->config->getIpWhiteList()->check($info['remote_ip'])){
                    $server->close($fd);
                    return;
                }
            }
            $data = self::dataUnPack($data);
            if($this->openssl instanceof Openssl){
                $data = $this->openssl->decrypt($data);
            }
            $data = json_decode($data,true);
            $client = new ClientInfo();
            $client->setFd($fd);
            $client->setReactorId($reactor_id);
            $client->setIp($info['remote_ip']);
            $response = new Response();
            if(is_array($data)){
                $caller = new Caller($data);
                $caller->setClient($client);
                if(isset($this->serviceList[$caller->getService()])){
                    $service = $this->serviceList[$caller->getService()];
                    (new $service($caller,$response));
                }else{
                    $response->setStatus(Response::STATUS_SERVICE_NOT_FOUND);
                }
                //响应分离的时候，不回复任何消息，也不断开连接，该场景用于异步
                if($response->getStatus() == Response::STATUS_RESPONSE_DETACH){
                    return;
                }
            }else{
                $response->setStatus(Response::STATUS_PACKAGE_ERROR);
            }
            $res = $response->__toString();
            if($this->openssl instanceof Openssl){
                $res = $this->openssl->encrypt($res);
            }
            $res = self::dataPack($res);
            $server->send($fd,$res);
            //短链接
            $server->close($fd);
        });
        /*
         * 如果配置了服务广播
         */
        if($this->config->isEnableBroadcast()){
            $broadcast = $server->addListener($this->config->getListenHost(),$this->config->getBroadcastListenPort(),SWOOLE_UDP);
            $broadcast->on('packet',function (\swoole_server $server, string $data, array $client_info){
                //这里做ip白名单过滤
                if($this->config->getIpWhiteList() instanceof IpWhiteList){
                    if(!$this->config->getIpWhiteList()->check($client_info['address'])){
                        return;
                    }
                }
                if($this->openssl instanceof Openssl){
                    $data = $this->openssl->decrypt($data);
                }
                $json = json_decode($data,true);
                if(is_array($json)){
                    $node = new ServiceNode($json);
                    $node->setIp($client_info['address']);
                    //刷新节点
                    $this->refreshServiceNode($node);
                }
            });
            //添加自定义进程做定时广播
            $server->addProcess(new \swoole_process(function (\swoole_process $process){
                pcntl_async_signals(true);
                //服务正常关闭的时候，对外广播服务下线
                $process::signal(SIGTERM,function ()use($process){
                    swoole_event_del($process->pipe);
                    $this->broadcastAllService(0);
                    $process->exit(0);
                });
                swoole_event_add($process->pipe, function()use($process){
                    $process->read(64 * 1024);
                });
                //服务启动后立即广播服务发现
                Timer::after(500,function (){
                    $this->broadcastAllService(time());
                });
                //默认5秒广播一次服务发现
                Timer::tick(5000,function (){
                    $this->broadcastAllService(time());
                });
            }));
        }
    }

    /*
     * 注册配置项的时候，创建swoole table
     */
    function setConfig(Config $config)
    {
        $this->config = $config;
        $this->swooleTable = new \swoole_table($this->config->getMaxNodes());
        $this->swooleTable->column('serviceName',\swoole_table::TYPE_STRING,45);
        $this->swooleTable->column('serviceId',\swoole_table::TYPE_STRING,8);
        $this->swooleTable->column('ip',\swoole_table::TYPE_STRING,15);
        $this->swooleTable->column('port',\swoole_table::TYPE_STRING,5);
        $this->swooleTable->column('lastHeartBeat',\swoole_table::TYPE_INT,8);
        $this->swooleTable->create();
        return $this;
    }
    /*
     * 刷新/注册一个服务节点
     */
    function refreshServiceNode(ServiceNode $serviceNode)
    {
        $this->swooleTable->set(substr(md5($serviceNode->getServiceId().$serviceNode->getServiceName()),8,16),$serviceNode->toArray());
        if($this->config->isEnableBroadcast()){
            $this->gcServiceNodes();
        }
    }
    /*
     * 获取全部服务节点
     */
    function getAllServiceNodes():array
    {
        $res = [];
        foreach ($this->swooleTable as $item){
            array_push($res,new ServiceNode($item));
        }
        return $res;
    }
    /*
     * 获取某个服务的全部节点
     */
    function getServiceNodes(string $serviceName):array
    {
        $res = [];
        foreach ($this->swooleTable as $item){
            if($item['serviceName'] == $serviceName){
                array_push($res,new ServiceNode($item));
            }
        }
        return $res;
    }
    /*
     * 获取某个服务的任意一个节点
     */
    function getServiceNode(string $serviceName):?ServiceNode
    {
        $list = $this->getServiceNodes($serviceName);
        if(!empty($list)){
            mt_srand();
            $data = $this->getServiceNodes($serviceName);
            return $data[mt_rand(0,count($data)-1)];
        }else{
            return null;
        }
    }

    private function gcServiceNodes()
    {
        foreach ($this->swooleTable as $key => $item){
            if(time() - $item['lastHeartBeat'] > 10){
                $this->swooleTable->del($key);
            }
        }
    }
    /*
     * 注册一个服务控制器
     */
    function registerService(string $serviceName,string $serviceClass)
    {
        if(!$this->config instanceof Config){
            throw new \Exception('Rpc Config is require');
        }

        if(!isset($this->serviceList[$serviceName])){
            try{
                $ref = new \ReflectionClass($serviceClass);
                if($ref->isSubclassOf(AbstractService::class)){
                    $this->serviceList[$serviceName] = $serviceClass;
                }else{
                    throw new \Exception("class {$serviceClass} is not a Rpc Service class");
                }
            }catch (\Throwable $throwable){
                Trigger::throwable($throwable);
            }
        }
        return $this;
    }

    /*
     * 获取一个客户端
     */
    function client():Client
    {
        if(!$this->config instanceof Config){
            throw new \Exception('Rpc Config is require');
        }
        return new Client($this->config);
    }

    public static function dataPack(string $sendStr):string
    {
        return pack('N', strlen($sendStr)).$sendStr;
    }

    public static function dataUnPack(string $rawData)
    {
        $len = unpack('N',$rawData);
        $data = substr($rawData,'4');
        if(strlen($data) != $len[1]){
            return null;
        }else{
            return $data;
        }
    }

    /**
     * @return null
     */
    public function getServerPort()
    {
        return $this->serverPort;
    }

    private function broadcast(string $msg,$addr,$port)
    {
        if($this->openssl instanceof Openssl){
            $msg = $this->openssl->encrypt($msg);
        }
        if(!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            Trigger::error($errormsg);
        }else{
            socket_set_option($sock,65535,SO_BROADCAST,true);
            socket_sendto($sock,$msg,strlen($msg),0,$addr,$port);
            socket_close($sock);
        }
    }

    private function broadcastAllService(int $time)
    {
        foreach ($this->serviceList as $serviceName => $serviceClass){
            $node = new ServiceNode();
            $node->setServiceName($serviceName);
            $node->setPort($this->serverPort);
            $node->setServiceId($this->config->getServiceId());
            //时间正确为上线,0为下线
            $node->setLastHeartBeat($time);
            $msg = $node->__toString();
            foreach ($this->config->getBroadcastList()->getList() as $address){
                $address = explode(':',$address);
                $this->broadcast($msg,$address[0],$address[1]);
            }
        }
    }
}