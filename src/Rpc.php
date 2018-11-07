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


    function __construct(Config $config)
    {
        $this->config = $config;

    }

    /*
     * 注册一个tcp服务作为RPC通讯服务
     */
    function attach(string $serviceName,\swoole_server $server):ActionList
    {
        $serviceList = new ActionList();
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
                'open_length_check' => false,
                'package_length_type'   => 'N',
                'package_length_offset' => 0,
                'package_body_offset'   => 4,
//                'package_max_length'    => $this->config->getMaxPackage(),
//                'heartbeat_idle_time' => $this->config->getHeartbeatIdleTime(),
//                'heartbeat_check_interval' => $this->config->getHeartbeatCheckInterval()
            ]
        );
        //注册 onReceive 回调
        $subPort->on('receive',function (\swoole_server $server, int $fd, int $reactor_id, string $data)use($serviceName){
            $json = json_decode(Pack::unpack($data),true);
            if(is_array($json)){
                $package = new Package($json);
                if(abs(time() - $package->getPackageTime()) < 2){
                    if($package->getSignature() === $package->generateSignature($this->config->getAuthKey())){
                        $server->send($fd,Pack::pack('assa'));
                        $server->send($fd,Pack::pack('bbbb'));
                        return;
                    }
                }
            }
            $server->close($fd);
        });
        return $serviceList;
    }
    /*
     * 每个进程中的client互相隔离
     */
    function client():Client
    {
        if(!$this->client){
            $this->client = new Client($this->config);
        }
        return $this->client();
    }

}