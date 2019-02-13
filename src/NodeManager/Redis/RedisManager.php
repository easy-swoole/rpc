<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-28
 * Time: 09:01
 */

namespace EasySwoole\Rpc\NodeManager\Redis;

use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use EasySwoole\Rpc\ServiceNode;


class RedisManager implements NodeManagerInterface
{
    private $pool;
    private $config;
    function __construct(Config $config)
    {
        $this->config = $config;
        PoolManager::getInstance()->registerAnonymous('__rpcRedis',function ()use($config){
            $redis = new \Redis();
            $redis->connect($config->getHost(),$config->getPort());
            if(!empty($config->getAuth())){
                $redis->auth($config->getAuth());
            }
            $redis->setOption(\Redis::OPT_SERIALIZER,\Redis::SERIALIZER_PHP);
            return $redis;
        });
        $this->pool = PoolManager::getInstance()->getPool('__rpcRedis');
    }

    function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        // TODO: Implement getServiceNodes() method.
        $data = $this->pool::invoke(function (\Redis $redis)use($serviceName){
            return $redis->hGetAll($this->config->getKeyName().$this->getServiceKey($serviceName));
        });
        if(!is_array($data)){
            return [];
        }
        foreach ($data as $key => $datum){
            if($datum['nodeExpire'] !== 0 && time() > $datum['nodeExpire']){
                unset($data[$key]);
                $this->pool::invoke(function (\Redis $redis)use($datum,$serviceName){
                    $redis->hDel($this->config->getKeyName().$this->getServiceKey($serviceName),$datum['nodeId']);
                });
            }else{
                $data[$key] = new ServiceNode($datum);
            }
        }
        if($version !== null){
            $temp = [];
            /** @var ServiceNode $item */
            foreach ($data as $item){
                if($item->getServiceVersion() == $version){
                    $temp[] = $item;
                }
            }
            return $temp;
        }
        return $data;
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        // TODO: Implement getServiceNode() method.
        $list = $this->getServiceNodes($serviceName,$version);
        if(empty($list)){
            return null;
        }
    }

    function allServiceNodes(): array
    {
        // TODO: Implement allServiceNodes() method.
    }

    function deleteServiceNode(ServiceNode $serviceNode)
    {
        // TODO: Implement deleteServiceNode() method.
    }

    function registerServiceNode(ServiceNode $serviceNode):bool
    {
        // TODO: Implement registerServiceNode() method.
        $array = $serviceNode->toArray();
        foreach ($array as $item){
            if($item === null){
                return false;
            }
        }
        $this->pool::invoke(function (\Redis $redis)use($array){
            return $redis->hSet($this->config->getKeyName().$this->getServiceKey($array['serviceName']),$array['nodeId'],$array);
        });
        return true;
    }

    private function getServiceKey($name):string
    {
        return substr(md5($name), 8, 16);
    }

}