<?php


namespace EasySwoole\Rpc\Config;


class Server
{
    protected $serverIp;
    protected $listenAddress = '0.0.0.0';
    protected $listenPort = 9600;
    protected $workerNum = 4;
    protected $maxPackageSize = 1024*1024*2;//2M
}