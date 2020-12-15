<?php


namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\Server\ServiceNode;

interface NodeManagerInterface
{
    function getNodes(string $serviceName,?int $version = null):array;
    function getNode(string $serviceName,?int $version = null):?ServiceNode;
    function offline(ServiceNode $serviceNode):bool ;
    function alive(ServiceNode $serviceNode):bool;
}