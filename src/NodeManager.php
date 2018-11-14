<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/12
 * Time: 10:34 PM
 */

namespace EasySwoole\Rpc;


use Swoole\Table;

class NodeManager implements NodeManagerInterface
{
    private $swooleTable;
    function __construct(int $maxNodes = 4096)
    {
        $this->swooleTable = new \swoole_table($maxNodes);
        $this->swooleTable->column('nodeId',Table::TYPE_STRING,8);
        $this->swooleTable->column('serviceName',Table::TYPE_STRING,32);
        $this->swooleTable->column('version',Table::TYPE_STRING,8);
        $this->swooleTable->column('ip',Table::TYPE_STRING,15);
        $this->swooleTable->column('port',Table::TYPE_INT);
        $this->swooleTable->column('lastHeartBeat',Table::TYPE_INT);
        $this->swooleTable->column('expire',Table::TYPE_INT);
        $this->swooleTable->create();
    }

    function getServiceNodes(string $serviceName,?string $version = null):array
    {
        return [];
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        // TODO: Implement getServiceNode() method.
        $node = new ServiceNode();
        $node->setIp('127.0.0.1');
        $node->setPort(9601);
        return $node;
    }
}