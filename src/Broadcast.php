<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/13
 * Time: 11:15 PM
 */

namespace EasySwoole\Rpc;


class Broadcast
{
    private $config;
    function __construct(Config $config)
    {
        $this->config = $config;
    }
}