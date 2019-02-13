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
    public static $PACKAGE_SETTING = [
        'open_length_check' => true,
        'package_length_type'   => 'N',
        'package_length_offset' => 0,
        'package_body_offset'   => 4,
    ];
    /**
     * @var $nodeManager NodeManagerInterface
     */
    protected $nodeManager;

    protected $serializeType = self::SERIALIZE_TYPE_RAW;

    protected $autoFindBroadcastAddress = [
        '127.0.0.1'
    ];

    protected $autoFindListenAddress = '127.0.0.1';

    protected $autoFindListenPort = 9600;

    /**
     * @return NodeManagerInterface
     */
    public function getNodeManager(): NodeManagerInterface
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

    /**
     * @return array
     */
    public function getAutoFindBroadcastAddress(): array
    {
        return $this->autoFindBroadcastAddress;
    }

    /**
     * @param array $autoFindBroadcastAddress
     */
    public function setAutoFindBroadcastAddress(array $autoFindBroadcastAddress): void
    {
        $this->autoFindBroadcastAddress = $autoFindBroadcastAddress;
    }

    /**
     * @return string
     */
    public function getAutoFindListenAddress(): string
    {
        return $this->autoFindListenAddress;
    }

    /**
     * @param string $autoFindListenAddress
     */
    public function setAutoFindListenAddress(string $autoFindListenAddress): void
    {
        $this->autoFindListenAddress = $autoFindListenAddress;
    }

    /**
     * @return int
     */
    public function getAutoFindListenPort(): ?int
    {
        return $this->autoFindListenPort;
    }

    /**
     * @param int $autoFindListenPort
     */
    public function setAutoFindListenPort(?int $autoFindListenPort = null): void
    {
        $this->autoFindListenPort = $autoFindListenPort;
    }

    protected function initialize(): void
    {
        if(empty($this->nodeId)){
           $this->nodeId = Random::character(8);
        }
    }
}