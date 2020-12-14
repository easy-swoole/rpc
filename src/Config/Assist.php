<?php


namespace EasySwoole\Rpc\Config;


class Assist
{
    /** @var int  */
    private $aliveInterval = 5000;
    /** @var UdpServiceFinder */
    private $udpServiceFinder;

    /**
     * @return int
     */
    public function getAliveInterval(): int
    {
        return $this->aliveInterval;
    }

    /**
     * @param int $aliveInterval
     */
    public function setAliveInterval(int $aliveInterval): void
    {
        $this->aliveInterval = $aliveInterval;
    }

    /**
     * @return UdpServiceFinder
     */
    public function getUdpServiceFinder(): UdpServiceFinder
    {
        if(!$this->udpServiceFinder){
            $this->udpServiceFinder = new UdpServiceFinder();
        }
        return $this->udpServiceFinder;
    }

    /**
     * @param UdpServiceFinder $udpServiceFinder
     */
    public function setUdpServiceFinder(UdpServiceFinder $udpServiceFinder): void
    {
        $this->udpServiceFinder = $udpServiceFinder;
    }
}