<?php


namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Rpc\ServiceNode;
use Swoole\Coroutine\Redis;

class RedisManager implements NodeManagerInterface
{
    private $pool;
    private $key = '__rpcRedis';
    private $expire_time = 5;

    public function __construct()
    {
        $config = ['host' => '127.0.0.1', 'port' => 6379];
        PoolManager::getInstance()->registerAnonymous('__rpcRedis', function () use ($config) {
            $redis = new Redis();
            $redis->connect($config['host'], $config['port']);
            if (!empty($config['auth'])) {
                $redis->auth($config['auth']);
            }
            return $redis;
        });
        $this->pool = PoolManager::getInstance()->getPool('__rpcRedis');
    }

    function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        $serviceNodes = [];
        if ($obj = $this->pool->getObj()) {
            $list = $obj->sMembers($this->key);
            foreach ($list as $serviceNodeKey) {
                list(, $serName, $serviceVersion) = explode('_', $serviceNodeKey);
                if ($serName == $serviceName) {
                    if (!is_null($version) && $serviceVersion !== $version) {
                        continue;
                    }
                    $serviceNode = $this->formatter($obj, $serviceNodeKey);
                    if (empty($serviceNode) || $serviceNode['lastHeartBeat'] + $this->expire_time < time()) {
                        $obj->srem($this->key, $serviceNodeKey);
                        continue;
                    }
                    array_push($serviceNodes, $serviceNode);
                }
            }
            $this->pool->recycleObj($obj);
        }
        return $serviceNodes;
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        $serviceNodes = $this->getServiceNodes($serviceName, $version);
        if (empty($serviceNodes)) {
            return null;
        }
        return new ServiceNode($serviceNodes[array_rand($serviceNodes)]);
    }

    function allServiceNodes(): array
    {
        $serviceNodes = [];
        if ($obj = $this->pool->getObj()) {
            $list = $obj->sMembers($this->key);
            foreach ($list as $serviceNodeKey) {
                $serviceNode = $this->formatter($obj, $serviceNodeKey);
                if (empty($serviceNode) || $serviceNode['lastHeartBeat'] + $this->expire_time < time()) {
                    $obj->srem($this->key, $serviceNodeKey);
                    continue;
                }
                array_push($serviceNodes, $serviceNode);
            }
            $this->pool->recycleObj($obj);
        }
        return $serviceNodes;
    }

    function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        if ($obj = $this->pool->getObj()) {
            $serviceNodeKey = $this->getServiceNodeKey($serviceNode->getNodeId(), $serviceNode->getServiceName(), $serviceNode->setServiceVersion());
            if (!$obj->sismember($this->key, $serviceNodeKey)) {
                $obj->srem($this->key, $serviceNodeKey);
            }
            $obj->delete($serviceNodeKey);
            $this->pool->recycleObj($obj);
        }
        return true;
    }

    function serviceNodeHeartBeat(ServiceNode $serviceNode): bool
    {
        if ($obj = $this->pool->getObj()) {
            $serviceNodeKey = $this->getServiceNodeKey($serviceNode->getNodeId(), $serviceNode->getServiceName(), $serviceNode->getServiceVersion());
            if (!$obj->sismember($this->key, $serviceNodeKey)) {
                $obj->sAdd($this->key, $serviceNodeKey);
            }
            if ($obj->exists($serviceNodeKey)) {//该节点是否存在
                $obj->hSet($serviceNodeKey, 'lastHeartBeat', $serviceNode->getLastHeartBeat());
            } else {
                $obj->hMSet($serviceNodeKey, $serviceNode->toArray());
            }
            $this->pool->recycleObj($obj);
        }
        return true;
    }


    private function getServiceNodeKey(string $serviceNode, string $serviceName, string $serviceVersion)
    {
        return implode('_', [$serviceNode, $serviceName, $serviceVersion]);
    }

    private function formatter(Redis $obj, string $serviceNodeKey)
    {
        $keys = $obj->hKeys($serviceNodeKey);
        $values = $obj->hVals($serviceNodeKey);
        return array_combine($keys, $values);
    }
}