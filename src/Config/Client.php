<?php


namespace EasySwoole\Rpc\Config;


class Client
{
    protected $maxPackageSize = 1024*1024*2;//2M
    /** @var null|callable $onGlobalSuccess */
    protected $onGlobalSuccess;
    /** @var null|callable $onGlobalFail */
    protected $onGlobalFail;
}