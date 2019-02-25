<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-25
 * Time: 20:52
 */

namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\ServiceNode;

interface NodeManagerInterface
{
    function __construct(Config $config);
    function getServiceNodes(string $serviceName,?string $version = null):array;
    function getServiceNode(string $serviceName,?string $version = null):?ServiceNode;
    function allServiceNodes():array ;
    function deleteServiceNode(ServiceNode $serviceNode):bool ;
    function registerServiceNode(ServiceNode $serviceNode):bool ;
}