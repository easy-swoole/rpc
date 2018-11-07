<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/7
 * Time: 8:24 PM
 */

namespace EasySwoole\Rpc;


class Client
{
    private $config;
    function __construct(Config $config)
    {
        $this->config = $config;
    }
}