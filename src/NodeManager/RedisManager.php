<?php


namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Component\Pool\PoolConf;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Rpc\ServiceNode;
use EasySwoole\Utility\Random;
use Swoole\Coroutine\Channel;
use Swoole\Runtime;

class RedisManager implements NodeManagerInterface
{
    protected $redisKey;
    /** @var Channel */
    protected $channel;
    function __construct(string $host,$port = 6379,$auth = null,string $hashKey = '__rpcNodes',int $maxRedisNum = 10)
    {
        $this->redisKey = $hashKey;
        Runtime::enableCoroutine();
        PoolManager::getInstance()->registerAnonymous('__rpcRedis',function (PoolConf $conf)use($host,$port,$auth,$maxRedisNum){
            $conf->setMaxObjectNum($maxRedisNum);
            $redis = new \Redis();
            $redis->connect($host,$port);
            if($auth){
                $redis->auth($auth);
            }
            $redis->setOption(\Redis::OPT_SERIALIZER,\Redis::SERIALIZER_PHP);
            return $redis;
        });
    }

    function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        $redis = PoolManager::getInstance()->getPool('__rpcRedis')->getObj(15);
        try{
            $nodes = $redis->hGet($this->redisKey,$serviceName);
            $nodes = $nodes ?: [];
            $ret = [];
            foreach ($nodes as $nodeId => $node){
                /**
                 * @var  $nodeId
                 * @var  ServiceNode $node
                 */
                if(time() - $node->getLastHeartBeat() > 30){
                    $this->deleteServiceNode($node);
                }
                if($version && $version != $node->getServiceVersion()){
                    continue;
                }
                $ret[$nodeId] = $node;

            }
            return $ret;
        }catch (\Throwable $throwable){
            //如果该redis断线则销毁
            PoolManager::getInstance()->getPool('__rpcRedis')->unsetObj($redis);
        }finally{
            //这边需要测试一个对象被unset后是否还能被回收
            PoolManager::getInstance()->getPool('__rpcRedis')->recycleObj($redis);
        }
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        $list = $this->getServiceNodes($serviceName,$version);
        return Random::arrayRandOne($list);
    }

    function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        $list = $this->getServiceNodes($serviceNode->getServiceName());
        if(isset($list[$serviceNode->getNodeId()])){
            unset($list[$serviceNode->getNodeId()]);
        }
        /** @var \Redis $redis */
        $redis = PoolManager::getInstance()->getPool('__rpcRedis')->getObj(15);
        try{
            $redis->hSet($this->redisKey,$serviceNode->getServiceName(),$list);
            return true;
        }catch (\Throwable $throwable){
            //如果该redis断线则销毁
            PoolManager::getInstance()->getPool('__rpcRedis')->unsetObj($redis);
        }finally{
            //这边需要测试一个对象被unset后是否还能被回收
            PoolManager::getInstance()->getPool('__rpcRedis')->recycleObj($redis);
        }
        return false;
    }

    function serviceNodeHeartBeat(ServiceNode $serviceNode): bool
    {
        $list = $this->getServiceNodes($serviceNode->getServiceName());
        if(empty($serviceNode->getLastHeartBeat())){
            $serviceNode->setLastHeartBeat(time());
        }
        $list[$serviceNode->getNodeId()] = $serviceNode;
        /** @var \Redis $redis */
        $redis = PoolManager::getInstance()->getPool('__rpcRedis')->getObj(15);
        try{
           $redis->hSet($this->redisKey,$serviceNode->getServiceName(),$list);
           return true;
        }catch (\Throwable $throwable){
            //如果该redis断线则销毁
            PoolManager::getInstance()->getPool('__rpcRedis')->unsetObj($redis);
        }finally{
            //这边需要测试一个对象被unset后是否还能被回收
            PoolManager::getInstance()->getPool('__rpcRedis')->recycleObj($redis);
        }
        return false;
    }
}