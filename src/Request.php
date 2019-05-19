<?php


namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class Request extends SplBean
{
    protected $serverName;
    protected $action;
    protected $arg;
}