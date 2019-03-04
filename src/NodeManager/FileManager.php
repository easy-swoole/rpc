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
    const FILE_PREFIX = 'easyswoole_rpc_';

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
        foreach ($list as $item) {
            $temp = new ServiceNode($item);
            if ($temp->getNodeExpire() !== 0 && time() > $temp->getNodeExpire()) {
                $this->deleteServiceNode($temp);
                continue;
            }
            if ($version !== null && $temp->getNodeId() != $version) {
                continue;
            }
            $ret[$temp->getNodeId()] = $temp->toArray();
        }
        return $ret;
    }

    function getServiceNode(string $serviceName, ?string $version = null): ?ServiceNode
    {
        $list = $this->getServiceNodes($serviceName, $version);
        $num = count($list);
        if ($num == 0) {
            return null;
        }
        return new ServiceNode(Random::arrayRandOne($list));
    }

    function allServiceNodes(): array
    {
        $list = [];
        $files = glob($this->saveDir . '/' . self::FILE_PREFIX . '*');
        if (!empty($files)) {
            foreach ($files as $file) {
                $nodeList = $this->getFileToArray($file);
                foreach ($nodeList as $item) {
                    $serviceNode = new ServiceNode($item);
                    if ($serviceNode->getNodeExpire() !== 0 && time() > $serviceNode->getNodeExpire()) {
                        $this->deleteServiceNode($serviceNode);
                        continue;
                    }
                    $list[$this->getKey($serviceNode)] = $serviceNode->toArray();//防止服务重启后，节点重复
                }
            }
        }
        return array_values($list);
    }

    private function getKey(ServiceNode $serviceNode): string
    {
        return substr(md5($serviceNode->getServiceIp() . $serviceNode->getServicePort() . $serviceNode->getServiceName() . $serviceNode->getServiceVersion()), 8, 16);
    }

    function deleteServiceNode(ServiceNode $serviceNode): bool
    {
        $all = $this->getServiceArray($serviceNode);
        if (isset($all[$serviceNode->getNodeId()])) {
            unset($all[$serviceNode->getNodeId()]);
        }
        $this->saveServiceArray($serviceNode, $all);
        return true;
    }

    function registerServiceNode(ServiceNode $serviceNode): bool
    {
        $data = $serviceNode->toArray();
        $all = $this->getServiceArray($serviceNode);
        $all[$serviceNode->getNodeId()] = $data;
        $this->saveServiceArray($serviceNode, $all);
        return true;
    }

    private function getServiceArray(ServiceNode $node): array
    {
        $file = $this->fileName($node);
        return $this->getFileToArray($file);
    }

    private function saveServiceArray(ServiceNode $node, array $data)
    {
        $file = $this->fileName($node);
        return $this->saveArrayToFile($file, $data);
    }

    private function fileName(ServiceNode $node)
    {
        return $this->saveDir . '/' . self::FILE_PREFIX . substr(md5($node->getServiceName()), 8, 16);
    }

    private function getFileToArray(string $file): array
    {
        if (file_exists($file)) {
            $data = file_get_contents($file);
            return unserialize($data);
        } else {
            return [];
        }
    }

    private function saveArrayToFile(string $file, array $array)
    {
        return file_put_contents($file, serialize($array), LOCK_EX);
    }
}
