<?php


namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\Server\ServiceNode;

interface NodeManagerInterface
{
    public function getNodes(string $serviceName, ?int $version = null): array;

    public function getNode(string $serviceName, ?int $version = null): ?ServiceNode;

    public function failDown(ServiceNode $serviceNode): bool;

    public function offline(ServiceNode $serviceNode): bool;

    public function alive(ServiceNode $serviceNode): bool;
}