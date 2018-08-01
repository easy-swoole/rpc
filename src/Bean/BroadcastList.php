<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/28
 * Time: 下午4:19
 */

namespace EasySwoole\Rpc\Bean;


class BroadcastList
{
    protected $list = [
        '255.255.255.255:9602'
    ];

    function add($address,$port)
    {
        array_push($this->list,"{$address}:{$port}");
        return $this;
    }

    function set(array $list)
    {
        $this->list = $list;
        return $this;
    }

    function getList()
    {
        return $this->list;
    }
}