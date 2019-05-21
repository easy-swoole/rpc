<?php


namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\ServiceNode;

interface NodeManagerInterface
{
    function getServiceNodes(string $serviceName,?string $version = null):array;
    function getServiceNode(string $serviceName,?string $version = null):?ServiceNode;
    function allServiceNodes():array ;
    function deleteServiceNode(ServiceNode $serviceNode):bool ;
    function serviceNodeHeartBeat(ServiceNode $serviceNode):bool ;
}