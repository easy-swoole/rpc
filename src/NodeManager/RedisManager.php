<?php


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

    function allServiceNodes(): array
    {
        // TODO: Implement allServiceNodes() method.
    }

    function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        // TODO: Implement deleteServiceNode() method.
    }

    function serviceNodeHeartBeat(ServiceNode $serviceNode): bool
    {
        return true;
    }
}