<?php


namespace EasySwoole\Rpc\Protocol;


use EasySwoole\Spl\SplBean;

class UdpPack extends SplBean
{
    const OP_ALIVE = 0x1;
    const OP_SHUTDOWN = 0x2;

    protected $op;
    protected $arg;
    /** @var int */
    protected $packTime;
    /** @var string|null */
    protected $fromNodeId;

    /**
     * @return mixed
     */
    public function getOp()
    {
        return $this->op;
    }

    /**
     * @param mixed $op
     */
    public function setOp($op): void
    {
        $this->op = $op;
    }

    /**
     * @return mixed
     */
    public function getArg()
    {
        return $this->arg;
    }

    /**
     * @param mixed $arg
     */
    public function setArg($arg): void
    {
        $this->arg = $arg;
    }

    /**
     * @return int
     */
    public function getPackTime(): int
    {
        return $this->packTime;
    }

    /**
     * @param int $packTime
     */
    public function setPackTime(int $packTime): void
    {
        $this->packTime = $packTime;
    }

    /**
     * @return string|null
     */
    public function getFromNodeId(): ?string
    {
        return $this->fromNodeId;
    }

    /**
     * @param string|null $fromNodeId
     */
    public function setFromNodeId(?string $fromNodeId): void
    {
        $this->fromNodeId = $fromNodeId;
    }
}