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
    const KEY = '__rpcRedisKey';
    private $pool;

    public function __construct(Config $config)
    {
        $config = $config->getExtra();
        PoolManager::getInstance()->registerAnonymous('__rpcRedis', function () use ($config) {
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port']);
            if (!empty($config['auth'])) {
                $redis->auth($config['auth']);
            }
            $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
            return $redis;
        });
        $this->pool = PoolManager::getInstance()->getPool('__rpcRedis');
    }

    function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        $list = [];
        $nodeList = $this->allServiceNodes();
        foreach ($nodeList as $item) {
            $serviceNode = new ServiceNode($item);
            if ($serviceNode->getServiceName() == $serviceName) {
                if ($version !== null && $serviceNode->getServiceVersion() != $version) {
                    continue;
                }
                $list[$serviceNode->getNodeId()] = $serviceNode->toArray();
            }
        }
        return $list;
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        $list = $this->getServiceNodes($serviceName, $version);
        $num = count($list);
        if ($num == 0) {
            return null;
        }
        return new ServiceNode(Random::arrayRandOne($list));
    }

    function allServiceNodes(): array
    {
        $list = [];
        if ($obj = $this->pool->getObj()) {
            $nodeList = $obj->hGetAll(self::KEY);
            $this->pool->recycleObj($obj);
            foreach ($nodeList as $key => $serviceNode) {
                if ($serviceNode->getNodeExpire() !== null && time() > $serviceNode->getNodeExpire()) {
                    $this->deleteServiceNode($serviceNode);//超时删除
                    continue;
                }
                $list[] = $serviceNode->toArray();
            }
        }
        return $list;
    }

    function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        if ($obj = $this->pool->getObj()) {
            $obj->hDel(self::KEY, $serviceNode->getNodeId());
            $this->pool->recycleObj($obj);
            return true;
        }
        return false;
    }

    function registerServiceNode(ServiceNode $serviceNode): bool
    {
        if ($obj = $this->pool->getObj()) {
            $obj->hSet(self::KEY, $serviceNode->getNodeId(), $serviceNode);
            $this->pool->recycleObj($obj);
            return true;
        }
        return false;
    }
}