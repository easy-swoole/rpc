<?php


namespace EasySwoole\Rpc\Network;


use EasySwoole\Rpc\Config\UdpServiceFinder;
use EasySwoole\Rpc\Protocol\UdpPack;

class UdpClient
{
    private $config;

    function __construct(UdpServiceFinder $finder)
    {
        $this->config = $finder;
    }

    function send(UdpPack $pack,string $ip,int $port)
    {

    }
}