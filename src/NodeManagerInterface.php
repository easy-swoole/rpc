<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/14
 * Time: 12:56 PM
 */

namespace EasySwoole\Rpc;


interface NodeManagerInterface
{
    function getServiceNodes(string $serviceName,?string $version = null):array;
    function getServiceNode(string $serviceName,?string $version = null):?ServiceNode;
    function refreshServiceNode(ServiceNode $serviceNode);
    function allServiceNodes():array ;
    function offlineServiceNode(ServiceNode $serviceNode);
}