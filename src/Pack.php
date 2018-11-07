<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/7
 * Time: 8:41 PM
 */

namespace EasySwoole\Rpc;


class Pack
{
    public static function pack(string $data):string
    {
        return pack('N', strlen($data)).$data;
    }

    public static function unpack(string $data):string
    {
        return substr($data,'4');
    }
}