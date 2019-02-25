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

class TableManager implements NodeManagerInterface
{

    public function __construct(Config $config)
    {

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