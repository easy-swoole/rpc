<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/1
 * Time: 下午3:15
 */

namespace EasySwoole\Rpc\Bean;


class IpWhiteList
{
    protected $list = ['127.0.0.1'];


    public function add(string $ip):IpWhiteList
    {
        array_push($this->list,$ip);
        return $this;
    }

    public function set(array $list):IpWhiteList
    {
        $this->list = $list;
        return $this;
    }

    public function getList():array
    {
        return $this->list;
    }

    public function check(string $ip)
    {
        return in_array($ip,$this->list);
    }

}