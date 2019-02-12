<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-28
 * Time: 09:01
 */

namespace EasySwoole\Rpc\NodeManager\Redis;


use EasySwoole\Component\Pool\AbstractPool;
use EasySwoole\Component\Pool\PoolConf;
use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use EasySwoole\Rpc\ServiceNode;


class RedisManager implements NodeManagerInterface
{
    protected $redisPool;
    function __construct(Config $config)
    {
        $this->redisPool = new class($config) extends AbstractPool{
            /** @var $redisConfig Config  */
            private $redisConfig;
            function __construct($config)
            {
                $this->redisConfig = $config;
                $conf = new PoolConf('redisPool');
                parent::__construct($conf);
            }

            protected function createObject()
            {
                // TODO: Implement createObject() method.
                $redis = new \Redis();
                $redis->connect($this->redisConfig->getHost(),$this->redisConfig->getPort());
                if(!empty($this->redisConfig->getAuth())){
                    $redis->auth($this->redisConfig->getAuth());
                }
                if($redis->ping()){
                    return $redis;
                }else{
                    return null;
                }
            }
        };
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

    function deleteServiceNode(ServiceNode $serviceNode)
    {
        // TODO: Implement deleteServiceNode() method.
    }

    function registerServiceNode(ServiceNode $serviceNode)
    {
        // TODO: Implement registerServiceNode() method.
    }

}