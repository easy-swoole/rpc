<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午2:55
 */

namespace EasySwoole\Rpc;



use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use EasySwoole\Utility\Random;

class Config extends ServiceNode
{
    const SERIALIZE_TYPE_JSON = 1;
    const SERIALIZE_TYPE_RAW = 2;
    /**
     * @var $nodeManager NodeManagerInterface
     */
    protected $nodeManager;

    protected $serializeType = self::SERIALIZE_TYPE_RAW;

    /**
     * @return NodeManagerInterface
     */
    public function getNodeManager(): ?NodeManagerInterface
    {
        return $this->nodeManager;
    }

    /**
     * @param NodeManagerInterface $nodeManager
     */
    public function setNodeManager(NodeManagerInterface $nodeManager): void
    {
        $this->nodeManager = $nodeManager;
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

    protected function initialize(): void
    {
        if(empty($this->nodeId)){
           $this->nodeId = Random::character(8);
        }
    }
}