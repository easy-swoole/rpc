<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-27
 * Time: 22:57
 */

namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\ServiceNode;

class RedisManager implements NodeManagerInterface
{
    function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        // TODO: Implement getServiceNodes() method.
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        // TODO: Implement getServiceNode() method.
    }

    function refreshServiceNode(ServiceNode $serviceNode)
    {
        // TODO: Implement refreshServiceNode() method.
    }

    function allServiceNodes(): array
    {
        // TODO: Implement allServiceNodes() method.
    }

    function offlineServiceNode(ServiceNode $serviceNode)
    {
        // TODO: Implement offlineServiceNode() method.
    }
}