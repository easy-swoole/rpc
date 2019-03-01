<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-02-25
 * Time: 14:05
 */

namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\ServiceNode;
use EasySwoole\Utility\Random;

class TableManager implements NodeManagerInterface
{
    private $tableManager;

    public function __construct(Config $config)
    {
        $extra = $config->getExtra();
        $this->tableManager = \EasySwoole\Component\TableManager::getInstance()->get($extra['tableName']);
    }

    function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        $list = [];
        $allNodeList = $this->allServiceNodes();
        foreach ($allNodeList as $nodeId => $item) {
            $serviceNode = new ServiceNode($item);
            if ($serviceNode->getServiceName() == $serviceName) {
                if ($version !== null && $serviceNode->getServiceVersion() != $version) {
                    continue;
                }
                $list[$nodeId] = $serviceNode->toArray();
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
        foreach ($this->tableManager as $nodeId => $item) {
            $serviceNode = new ServiceNode($item);
            if ($serviceNode->getNodeExpire() !== null && time() > $serviceNode->getNodeExpire()) {
                $this->deleteServiceNode($serviceNode);//超时删除
                continue;
            }
            $list[] = $serviceNode->toArray();
        }
        return $list;
    }

    function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        return $this->tableManager->del($serviceNode->getNodeId());
    }

    function registerServiceNode(ServiceNode $serviceNode): bool
    {
        $this->tableManager->set($serviceNode->getNodeId(), $serviceNode->toArray());
        return true;
    }
}