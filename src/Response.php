<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/12
 * Time: 10:46 PM
 */

namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class Response extends SplBean
{
    const STATUS_OK = 'OK';
    const STATUS_NODES_EMPTY = 'NODES_EMPTY';
    const STATUS_CONNECT_TIMEOUT = 'CONNECT_TIMEOUT';
    const STATUS_SERVER_TIMEOUT = 'SERVER_TIMEOUT';
    const STATUS_SERVER_ERROR = 'SERVER_ERROR';
    const STATUS_SERVER_ACTION_MISS = 'ACTION_MISS';
    protected $message;
    protected $status;

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message): void
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status): void
    {
        $this->status = $status;
    }

}