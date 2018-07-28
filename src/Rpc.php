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

class Rpc
{
    use Singleton;
    private $serviceList = [];
    private $openssl = null;
    private $swooleTable;
    private $config;

    /*
     * 请勿覆盖pack check方式
     */
    function attach(\swoole_server $server,Config $config)
    {
        $this->config = $config;

        $this->swooleTable = new \swoole_table($config->getMaxNodes());

        if($config->isSubServerMode()){
            $server = $server->addListener($config->getListenHost(),$config->getServerPort(),SWOOLE_TCP);
        }

        $server->set(
            [
                'open_length_check' => true,
                'package_length_type'   => 'N',
                'package_length_offset' => 0,
                'package_body_offset'   => 4,
                'package_max_length'    => $config->getMaxPackage(),
                'heartbeat_idle_time' => $config->getHeartbeatIdleTime(),
                'heartbeat_check_interval' => $config->getHeartbeatCheckInterval()
            ]
        );

        if(!empty($config->getSecretKey())){
            $this->openssl = new Openssl($config->getSecretKey());
        }

        $server->on('receive',function (\swoole_server $server, int $fd, int $reactor_id, string $data){

        });

        if($config->isEnableBroadcast()){
            $broadcast = $server->addListener($config->getListenHost(),$config->getBroadcastListenPort(),SWOOLE_UDP);
            $broadcast->on('packet',function (\swoole_server $server, string $data, array $client_info){

            });
        }

    }

    function registerService(string $serviceName):Service
    {
        if(!isset($this->serviceList[$serviceName])){
            $this->serviceList[$serviceName] = new Service($serviceName);
        }
        return $this->serviceList[$serviceName];
    }

    function client():Client
    {
        if(empty($this->config)){
            throw new \Exception('Rpc Config is required');
        }
        return new Client($this->config);
    }
}