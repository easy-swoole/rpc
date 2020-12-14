<?php


namespace EasySwoole\Rpc\Config;


class Assist
{
    protected $listenAddress = '0.0.0.0';
    protected $listenPort = 9601;
    protected $broadcastAddress = ['127.0.0.1'];
    protected $interval = 5;
    protected $enableListen = true;
    protected $enableBroadcast = true;
    protected $secretKey = '';
}