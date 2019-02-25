<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-02-25
 * Time: 14:46
 */

namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\ServiceNode;

class RedisManager implements NodeManagerInterface
{
    private $pool;
    public function __construct(Config $config)
    {
        $config = $config->getExtra();
        PoolManager::getInstance()->registerAnonymous('__rpcRedis',function ()use($config){
            $redis = new \Redis();
            $redis->connect($config['host'],$config['port']);
            if(!empty($config['auth'])){
                $redis->auth($config['auth']);
            }
            $redis->setOption(\Redis::OPT_SERIALIZER,\Redis::SERIALIZER_PHP);
            return $redis;
        });
        $this->pool = PoolManager::getInstance()->getPool('__rpcRedis');
    }

    function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        // TODO: Implement getServiceNodes() method.
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        // TODO: Implement getServiceNode() method.
    }

    function allServiceNodes(): array
    {
        // TODO: Implement allServiceNodes() method.
    }

    function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        // TODO: Implement deleteServiceNode() method.
    }

    function registerServiceNode(ServiceNode $serviceNode): bool
    {
        // TODO: Implement registerServiceNode() method.
    }
}