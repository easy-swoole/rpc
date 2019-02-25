<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午2:55
 */

namespace EasySwoole\Rpc;



use EasySwoole\Rpc\AutoFind\ProcessConfig;
use EasySwoole\Rpc\Exception\Exception;
use EasySwoole\Rpc\NodeManager\FileManager;
use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use EasySwoole\Utility\Random;

class Config extends ServiceNode
{
    const SERIALIZE_TYPE_JSON = 1;
    const SERIALIZE_TYPE_RAW = 2;
    protected $packageSetting = [
        'open_length_check' => true,
        'package_length_type'   => 'N',
        'package_length_offset' => 0,
        'package_body_offset'   => 4,
    ];
    /**
     * @var $nodeManager NodeManagerInterface
     */
    protected $nodeManager = FileManager::class;

    protected $serializeType = self::SERIALIZE_TYPE_RAW;

    protected $autoFindConfig;

    protected $extra = [];

    /**
     * @return NodeManagerInterface
     */
    public function getNodeManager(): NodeManagerInterface
    {
        if(is_string($this->nodeManager)){
            $this->nodeManager = new $this->nodeManager($this);
        }
        return $this->nodeManager;
    }

    public function setNodeManager(string $nodeManager): void
    {
        $ref = new \ReflectionClass($nodeManager);
        if($ref->implementsInterface(NodeManagerInterface::class)){
            $this->nodeManager = $nodeManager;
        }else{
            throw new Exception("{$nodeManager} not a class of nodeManagerInterface");
        }
    }

    /**
     * @return int
     */
    public function getSerializeType(): int
    {
        return $this->serializeType;
    }

    /**
     * @param int $serializeType
     */
    public function setSerializeType(int $serializeType): void
    {
        $this->serializeType = $serializeType;
    }

    public function getAutoFindConfig():ProcessConfig
    {
        return $this->autoFindConfig;
    }

    /**
     * @return array
     */
    public function getPackageSetting(): array
    {
        return $this->packageSetting;
    }

    public function setMaxPackageLength(int $len)
    {
        $this->packageSetting['package_max_length'] = $len;
    }

    /**
     * @return array
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * @param array $extra
     */
    public function setExtra(array $extra): void
    {
        $this->extra = $extra;
    }

    protected function initialize(): void
    {
        if(empty($this->nodeId)){
           $this->nodeId = Random::character(8);
        }
        if(empty($this->autoFindConfig)){
            $this->autoFindConfig = new ProcessConfig();
        }
    }
}