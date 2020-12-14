<?php


namespace EasySwoole\Rpc\Protocol;


use EasySwoole\Spl\SplBean;

class UdpPack extends SplBean
{
    const OP_ALIVE = 0x1;
    const OP_SHUTDOWN = 0x2;

    private $op;
    private $agr;
    /** @var string */
    private $signature;
    /** @var int */
    private $time;

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
    public function getAgr()
    {
        return $this->agr;
    }

    /**
     * @param mixed $agr
     */
    public function setAgr($agr): void
    {
        $this->agr = $agr;
    }

    /**
     * @return string
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     */
    public function setSignature(string $signature): void
    {
        $this->signature = $signature;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }

    /**
     * @param int $time
     */
    public function setTime(int $time): void
    {
        $this->time = $time;
    }
}