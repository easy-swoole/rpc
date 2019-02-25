<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-02-25
 * Time: 13:54
 */

namespace EasySwoole\Rpc\NodeManager;


use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\ServiceNode;
use EasySwoole\Utility\Random;

class FileManager implements NodeManagerInterface
{
    private $saveDir = '';

    public function __construct(Config $config)
    {
        $this->saveDir = sys_get_temp_dir();
    }

    function getServiceNodes(string $serviceName, ?string $version = null): array
    {
        // TODO: Implement getServiceNodes() method.
        $temp = new ServiceNode();
        $temp->setServiceName($serviceName);
        $list = $this->getServiceArray($temp);
        $ret = [];
        foreach ($list as $item){
            $temp = new ServiceNode($item);
            if($temp->getNodeExpire() != 0 && time() > $temp->getNodeExpire()){
                $this->deleteServiceNode($temp);
                continue;
            }
            if($version !== null && $temp->getNodeId() != $version){
                continue;
            }
            $ret[$temp->getNodeId()] = $temp;
        }
        return $ret;
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        // TODO: Implement getServiceNode() method.
        $list = $this->getServiceNodes($serviceName,$version);
        $num = count($list);
        if($num == 0){
            return null;
        }
       return Random::arrayRandOne($list);
    }

    function allServiceNodes(): array
    {
        // TODO: Implement allServiceNodes() method.
        return [];
    }

    function deleteServiceNode(ServiceNode $serviceNode):bool
    {
        $all = $this->getServiceArray($serviceNode);
        if(isset($all[$serviceNode->getNodeId()])){
            unset($all[$serviceNode->getNodeId()]);
        }
        $this->saveServiceArray($serviceNode,$all);
        return true;
    }

    function registerServiceNode(ServiceNode $serviceNode):bool
    {
        $data = $serviceNode->toArray();
        $all = $this->getServiceArray($serviceNode);
        $all[$serviceNode->getNodeId()] = $data;
        $this->saveServiceArray($serviceNode,$all);
        return true;
    }

    private function getServiceArray(ServiceNode $node):array
    {
        $file = $this->fileName($node);
        return $this->getFileToArray($file);
    }

    private function saveServiceArray(ServiceNode $node,array $data)
    {
        $file = $this->fileName($node);
        return $this->saveArrayToFile($file,$data);
    }

    private function fileName(ServiceNode $node)
    {
        return $this->saveDir.'/'.substr(md5($node->getServiceName()),8,16);
    }

    private function getFileToArray(string $file):array
    {
        if(file_exists($file)){
            $data = file_get_contents($file);
            return unserialize($data);
        }else{
            return [];
        }
    }

    private function saveArrayToFile(string $file,array $array)
    {
        return file_put_contents($file,serialize($array),LOCK_EX);
    }
}