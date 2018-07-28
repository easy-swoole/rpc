<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午4:19
 */

namespace EasySwoole\Rpc\Bean;


use EasySwoole\Spl\SplBean;

class BroadcastList extends SplBean
{
    protected $list = [
        '255.255.255.255:9601'
    ];

    function add($address,$port)
    {
        array_push($this->list,"{$address}:{$port}");
        return $this;
    }

    function clear()
    {
        $this->list = [];
        return $this;
    }

    function getList()
    {
        return $this->list;
    }
}