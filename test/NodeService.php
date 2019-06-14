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
use EasySwoole\Rpc\NodeManager\TableManager as nodeTableManager;

class NodeService extends AbstractService
{
    public function getNodeManager(): nodeTableManager
    {
        $table = TableManager::getInstance()->get('__rpcRedis');
        var_dump(432423423);
        return new nodeTableManager($table);
    }

    public function serviceName(): string
    {
        return 'NodeService';
    }

    public function getServiceNodes()
    {
        $args = $this->request()->getArg();
        $serviceName = $args['serviceName'];
        $version = $args['version'] ?? null;
        $list = $this->getNodeManager()->getServiceNodes($serviceName, $version);
        $this->response()->setResult($list);
    }

    public function getServiceNode()
    {
        $args = $this->request()->getArg();
        $serviceName = $args['serviceName'];
        $version = $args['version'] ?? null;
        $node = $this->getNodeManager()->getServiceNode($serviceName, $version);
        $result = empty($node) ? [] : $node->toArray();
        $this->response()->setResult($result);
    }

    public function deleteServiceNode()
    {
        $args = $this->request()->getArg();
        $serviceName = $args['serviceName'];
        $version = $args['version'] ?? null;
        $node = $this->getNodeManager()->getServiceNode($serviceName, $version);
        if (!empty($node)) {
            $ret = $this->getNodeManager()->deleteServiceNode($node);
            var_dump($ret);
        }
        $this->response()->setResult(['msg' => '删除成功']);
    }
}