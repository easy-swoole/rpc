<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/5/30
 * Time: 15:00
 */

namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\ServiceNode;
use EasySwoole\Component\TableManager as esTableManager;
use Swoole\Table;

class TableManager implements NodeManagerInterface
{
    private $table;
    private $expire_time = 5;

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        $serviceNodes = [];
        foreach ($this->table as $serviceNodeKey => $serviceNode) {
            if ($serviceNode['lastHeartBeat'] + $this->expire_time < time()) {
                $this->table->del($serviceNodeKey);
                continue;
            }
            if ($serviceNode['serviceName'] == $serviceName) {
                if (!is_null($version)) {
                    if ($serviceNode['serviceVersion'] != $version) {
                        continue;
                    }
                }
                array_push($serviceNodes, $serviceNode);
            }
        }
        return $serviceNodes;
    }

    public function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        $serviceNodes = $this->getServiceNodes($serviceName, $version);
        if (empty($serviceNodes)) {
            return null;
        }
        return new ServiceNode($serviceNodes[array_rand($serviceNodes)]);
    }

    public function allServiceNodes(): array
    {
        $serviceNodes = [];
        foreach ($this->table as $serviceNodeKey => $serviceNode) {
            if ($serviceNode['lastHeartBeat'] + $this->expire_time < time()) {
                $this->table->del($serviceNodeKey);
                continue;
            }
            array_push($serviceNodes, $serviceNode);
        }
        return $serviceNodes;
    }

    public function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        $serviceNodeKey = $this->getServiceNodeKey($serviceNode->getNodeId(), $serviceNode->getServiceName(), $serviceNode->getServiceVersion());
        $serviceNode = $this->table->get($serviceNodeKey);
        if (!empty($serviceNode)) {
            $this->table->del($serviceNodeKey);
        }
        return true;
    }

    public function serviceNodeHeartBeat(ServiceNode $serviceNode): bool
    {
        $serviceNodeKey = $this->getServiceNodeKey($serviceNode->getNodeId(), $serviceNode->getServiceName(), $serviceNode->getServiceVersion());
        $this->table->set($serviceNodeKey, $serviceNode->toArray());
        return true;
    }

    private function getServiceNodeKey(string $serviceNodeId, string $serviceName, string $serviceVersion)
    {
        return implode('_', [$serviceNodeId, $serviceName, $serviceVersion]);
    }
}