<?php


namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\Server\ServiceNode;

interface NodeManagerInterface
{
    function getNodes(string $serviceName,?string $version = null):array;
    function getNode(string $serviceName,?string $version = null):?ServiceNode;
    function deleteNode(ServiceNode $serviceNode):bool ;
    function alive(ServiceNode $serviceNode):bool;
    function healthCheck();
}