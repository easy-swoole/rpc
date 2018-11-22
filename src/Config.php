<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午2:55
 */

namespace EasySwoole\Rpc;



use EasySwoole\Utility\Random;

class Config
{
    private $authKey;
    private $enableOpenssl = false;
    private $listenPort = 9601;
    private $listenAddress = '0.0.0.0';
    private $nodeId;
    private $serviceIp;
    private $maxPackage = 1024*1024;
    private $heartbeatIdleTime = 30;
    private $heartbeatCheckInterval = 30;
    private $onActionMiss;
    private $onException;
    private $onBroadcast;
    private $onBroadcastReceive;
    private $onShutdown;
    private $onRequest;
    private $afterRequest;

    private $protocolSetting = [
        'open_length_check' => true,
        'package_length_type'   => 'N',
        'package_length_offset' => 0,
        'package_body_offset'   => 4,
    ];

    private $broadcastAddress = ['255.255.255.255:9600'];
    private $broadcastListenAddress = '0.0.0.0';
    private $broadcastListenPort = 9600;//广播服务端的UDP监听端口
    private $broadcastTTL = 15;//多久执行一次广播
    private $nodeExpire = 18;//表示节点过多久失效

    private $serviceName;
    private $serviceVersion = '1.0.0';

    private $nodeManager = NodeManager::class;

    function __construct()
    {
        $this->nodeId = Random::character(8);
        $this->onActionMiss = function (\swoole_server $server,RequestPackage $requestPackage,Response $response,int $fd){
                $response->setStatus($response::STATUS_SERVER_ACTION_MISS);
                $response->setMessage("action : {$requestPackage->getAction()} miss");
        };
        $this->onException = function (\Throwable $throwable,RequestPackage $package,Response $response, \swoole_server $server, int $fd){
            $response->setStatus($response::STATUS_SERVER_ERROR);
            $response->setMessage("{$throwable->getMessage()} at file {$throwable->getFile()} line {$throwable->getLine()}");
        };
    }

    /**
     * @return bool
     */
    public function isEnableOpenssl(): bool
    {
        return $this->enableOpenssl;
    }

    /**
     * @param bool $enableOpenssl
     */
    public function setEnableOpenssl(bool $enableOpenssl): void
    {
        $this->enableOpenssl = $enableOpenssl;
    }
    /**
     * @return mixed
     */
    public function getServiceIp()
    {
        return $this->serviceIp;
    }

    /**
     * @param mixed $serviceIp
     */
    public function setServiceIp($serviceIp): void
    {
        $this->serviceIp = $serviceIp;
    }

    /**
     * @return mixed
     */
    public function getOnBroadcast()
    {
        return $this->onBroadcast;
    }

    /**
     * @param mixed $onBroadcast
     */
    public function setOnBroadcast(callable $onBroadcast): void
    {
        $this->onBroadcast = $onBroadcast;
    }

    /**
     * @return mixed
     */
    public function getOnBroadcastReceive()
    {
        return $this->onBroadcastReceive;
    }

    /**
     * @param mixed $onBroadcastReceive
     */
    public function setOnBroadcastReceive(callable $onBroadcastReceive): void
    {
        $this->onBroadcastReceive = $onBroadcastReceive;
    }

    /**
     * @return mixed
     */
    public function getOnShutdown()
    {
        return $this->onShutdown;
    }

    /**
     * @param mixed $onShutdown
     */
    public function setOnShutdown(callable $onShutdown): void
    {
        $this->onShutdown = $onShutdown;
    }

    /**
     * @return mixed
     */
    public function getOnRequest()
    {
        return $this->onRequest;
    }

    /**
     * @param mixed $onRequest
     */
    public function setOnRequest(callable $onRequest): void
    {
        $this->onRequest = $onRequest;
    }

    /**
     * @return mixed
     */
    public function getAfterRequest()
    {
        return $this->afterRequest;
    }

    /**
     * @param mixed $afterRequest
     */
    public function setAfterRequest(callable $afterRequest): void
    {
        $this->afterRequest = $afterRequest;
    }


    /**
     * @return string
     */
    public function getNodeManager(): string
    {
        return $this->nodeManager;
    }

    public function setNodeManager(string $nodeManager): void
    {
        $ref = new \ReflectionClass($nodeManager);
        if($ref->implementsInterface(NodeManagerInterface::class)){
            $this->nodeManager = $nodeManager;
        }
    }

    /**
     * @return mixed
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @param mixed $serviceName
     */
    public function setServiceName($serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @return string
     */
    public function getServiceVersion(): string
    {
        return $this->serviceVersion;
    }

    /**
     * @param string $serviceVersion
     */
    public function setServiceVersion(string $serviceVersion): void
    {
        $this->serviceVersion = $serviceVersion;
    }



    /**
     * @return array
     */
    public function getBroadcastAddress(): array
    {
        return $this->broadcastAddress;
    }

    /**
     * @param array $broadcastAddress
     */
    public function setBroadcastAddress(array $broadcastAddress): void
    {
        $this->broadcastAddress = $broadcastAddress;
    }

    /**
     * @return string
     */
    public function getBroadcastListenAddress(): string
    {
        return $this->broadcastListenAddress;
    }

    /**
     * @param string $broadcastListenAddress
     */
    public function setBroadcastListenAddress(string $broadcastListenAddress): void
    {
        $this->broadcastListenAddress = $broadcastListenAddress;
    }

    /**
     * @return int
     */
    public function getBroadcastListenPort(): int
    {
        return $this->broadcastListenPort;
    }

    /**
     * @param int $broadcastListenPort
     */
    public function setBroadcastListenPort(int $broadcastListenPort): void
    {
        $this->broadcastListenPort = $broadcastListenPort;
    }

    /**
     * @return int
     */
    public function getBroadcastTTL(): int
    {
        return $this->broadcastTTL;
    }

    /**
     * @param int $broadcastTTL
     */
    public function setBroadcastTTL(int $broadcastTTL): void
    {
        $this->broadcastTTL = $broadcastTTL;
    }

    /**
     * @return int
     */
    public function getNodeExpire(): int
    {
        return $this->nodeExpire;
    }

    /**
     * @param int $nodeExpire
     */
    public function setNodeExpire(int $nodeExpire): void
    {
        $this->nodeExpire = $nodeExpire;
    }


    function getProtocolSetting():array
    {
        return $this->protocolSetting + [
                'package_max_length'    => $this->maxPackage,
                'heartbeat_idle_time' => $this->heartbeatIdleTime,
                'heartbeat_check_interval' => $this->heartbeatCheckInterval
            ];
    }

    public function onException(callable $callback)
    {
        $this->onException = $callback;
    }

    public function onActionMiss(callable $callback)
    {
        $this->onActionMiss = $callback;
        return $this;
    }

    /**
     * @return callable
     */
    public function getOnActionMiss()
    {
        return $this->onActionMiss;
    }

    /**
     * @return callable
     */
    public function getOnException()
    {
        return $this->onException;
    }

    /**
     * @return int
     */
    public function getListenPort()
    {
        return $this->listenPort;
    }

    /**
     * @param int $listenPort
     */
    public function setListenPort($listenPort): void
    {
        $this->listenPort = $listenPort;
    }

    /**
     * @return mixed
     */
    public function getAuthKey()
    {
        return $this->authKey;
    }

    /**
     * @param mixed $authKey
     */
    public function setAuthKey($authKey): void
    {
        $this->authKey = $authKey;
    }

    /**
     * @return string
     */
    public function getListenAddress(): string
    {
        return $this->listenAddress;
    }

    /**
     * @param string $listenAddress
     */
    public function setListenAddress(string $listenAddress): void
    {
        $this->listenAddress = $listenAddress;
    }

    /**
     * @return mixed
     */
    public function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * @param mixed $nodeId
     */
    public function setNodeId($nodeId): void
    {
        $this->nodeId = $nodeId;
    }

    /**
     * @return float|int
     */
    public function getMaxPackage()
    {
        return $this->maxPackage;
    }

    /**
     * @param float|int $maxPackage
     */
    public function setMaxPackage($maxPackage): void
    {
        $this->maxPackage = $maxPackage;
    }

    /**
     * @return int
     */
    public function getHeartbeatIdleTime(): int
    {
        return $this->heartbeatIdleTime;
    }

    /**
     * @param int $heartbeatIdleTime
     */
    public function setHeartbeatIdleTime(int $heartbeatIdleTime): void
    {
        $this->heartbeatIdleTime = $heartbeatIdleTime;
    }

    /**
     * @return int
     */
    public function getHeartbeatCheckInterval(): int
    {
        return $this->heartbeatCheckInterval;
    }

    /**
     * @param int $heartbeatCheckInterval
     */
    public function setHeartbeatCheckInterval(int $heartbeatCheckInterval): void
    {
        $this->heartbeatCheckInterval = $heartbeatCheckInterval;
    }
}