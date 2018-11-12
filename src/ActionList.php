<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/7
 * Time: 8:34 PM
 */

namespace EasySwoole\Rpc;


class ActionList
{
    private $list = [];

    function register(string $actionName,callable $callback):ActionList
    {
        $this->list[$actionName] = $callback;
        return $this;
    }

    function __getAction(?string $actionName):?callable
    {
        if(isset($this->list[$actionName])){
            return $this->list[$actionName];
        }else{
            return null;
        }
    }
}