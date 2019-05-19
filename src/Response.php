<?php


namespace EasySwoole\Rpc;


class Response
{
    const STATUS_OK = 'OK';
    const STATUS_NODES_EMPTY = 'NODES_EMPTY';
    const STATUS_CONNECT_TIMEOUT = 'CONNECT_TIMEOUT';
    const STATUS_SERVER_TIMEOUT = 'SERVER_TIMEOUT';
    const STATUS_SERVER_ERROR = 'SERVER_ERROR';
    const STATUS_SERVER_ACTION_MISS = 'ACTION_MISS';

    protected $result;
    protected $status = self::STATUS_OK;
    protected $nodeId;
}