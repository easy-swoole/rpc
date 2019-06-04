<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/5/30
 * Time: 17:35
 */

namespace EasySwoole\Rpc\Test;


use EasySwoole\Component\TableManager;
use EasySwoole\Rpc\AbstractService;

class NodeService extends AbstractService
{
    public function serviceName(): string
    {
        return 'NodeService';
    }

    public function allServiceNodes()
    {
        $table = TableManager::getInstance()->get('__rpcRedis');
        $tableManager = new \EasySwoole\Rpc\NodeManager\TableManager($table);
        $list = $tableManager->allServiceNodes();
        $this->response()->setResult($list);
    }

    public function getServiceNodes()
    {
        $args = $this->request()->getArg();
        $serviceName = $args['serviceName'];
        $version = $args['version'] ?? null;
        $table = TableManager::getInstance()->get('__rpcRedis');
        $tableManager = new \EasySwoole\Rpc\NodeManager\TableManager($table);
        $list = $tableManager->getServiceNodes($serviceName, $version);
        $this->response()->setResult($list);
    }

    public function getServiceNode()
    {
        $args = $this->request()->getArg();
        $serviceName = $args['serviceName'];
        $version = $args['version'] ?? null;
        $table = TableManager::getInstance()->get('__rpcRedis');
        $tableManager = new \EasySwoole\Rpc\NodeManager\TableManager($table);
        $node = $tableManager->getServiceNode($serviceName, $version);
        $result = empty($node) ? [] : $node->toArray();
        $this->response()->setResult($result);
    }

    public function deleteServiceNode()
    {
        $args = $this->request()->getArg();
        $serviceName = $args['serviceName'];
        $version = $args['version'] ?? null;
        $table = TableManager::getInstance()->get('__rpcRedis');
        $tableManager = new \EasySwoole\Rpc\NodeManager\TableManager($table);
        $node = $tableManager->getServiceNode($serviceName, $version);
        if (!empty($node)) {
            $tableManager->deleteServiceNode($node);
        }
        $this->response()->setResult(['msg' => '删除成功']);
    }
}