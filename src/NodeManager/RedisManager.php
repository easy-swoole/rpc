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
use EasySwoole\Utility\Random;

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
        $temp = new ServiceNode();
        $temp->setServiceName($serviceName);
        $list = $this->getServiceArray($temp);
        $ret = [];
        foreach ($list as $item){
            $temp = new ServiceNode($item);
            if($temp->getNodeExpire() !== 0 && time() > $temp->getNodeExpire()){
                $this->deleteServiceNode($temp);
                continue;
            }
            if($version !== null && $temp->getNodeId() != $version){
                continue;
            }
            $ret[$temp->getNodeId()] = $temp;
        }
        return $ret;
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        // TODO: Implement getServiceNode() method.
        $list = $this->getServiceNodes($serviceName,$version);
        $num = count($list);
        if($num == 0){
            return null;
        }
        return Random::arrayRandOne($list);
    }

    function allServiceNodes(): array
    {
        // TODO: Implement allServiceNodes() method.
        return [];
    }

    function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        // TODO: Implement deleteServiceNode() method.
        $domain = $this->doMain($serviceNode);
        if ($this->pool->hExists($domain,$serviceNode->getNodeId())) {
            $this->pool->hDel($serviceNode->getNodeId());
        }
        return true;
    }

    function registerServiceNode(ServiceNode $serviceNode): bool
    {
        // TODO: Implement registerServiceNode() method.
        $data = $serviceNode->toArray();
        $this->saveServiceArray($serviceNode,$data);
        return true;

    }

    private function getServiceArray(ServiceNode $node) :array
    {
        $domain = $this->doMain($node);
        return $this->getRedisToArray($domain);
    }

    private function saveServiceArray(ServiceNode $node, array $data)
    {
        $domain = $this->doMain($node);
        return $this->saveArrayToRedis($domain,$node->getNodeId(),$data);
    }

    private function getRedisToArray(String $key)
    {
        return $data = $this->pool->hGetAll($key);
    }

    private function doMain(ServiceNode $node)
    {
        return substr(md5($node->getServiceName()),8,16);
    }

    private function saveArrayToRedis(string $key,int $nodeId,array $data)
    {
        return $this->pool->hSet($key,$nodeId,serialize($data));
    }
}