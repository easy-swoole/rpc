<?php


namespace EasySwoole\Rpc;


class Rpc
{
    protected $config;
    protected $list = [];
    function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getConfig():Config
    {
        return $this->config;
    }

    public function add()
    {

    }
}