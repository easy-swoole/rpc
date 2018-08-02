<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/27
 * Time: 下午10:56
 */

namespace EasySwoole\Rpc\Bean;


use EasySwoole\Spl\SplBean;

class Response extends SplBean
{
    const STATUS_SERVICE_NOT_FOUND = 'SERVICE_NOT_FOUND';
    const STATUS_CONNECT_FAIL = 'CONNECT_FAIL';
    const STATUS_SERVICE_ERROR = 'SERVICE_ERROR';
    const STATUS_SERVICE_TIMEOUT = 'SERVICE_TIMEOUT';
    const STATUS_PACKAGE_ERROR = 'PACKAGE_ERROR';
    const STATUS_SERVICE_OK = 'OK';

    const STATUS_RESPONSE_DETACH = 'RESPONSE_DETACH';//不响应客户端，可能是在异步时返回。
    protected $status;
    protected $result;
    protected $message;

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

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param mixed $result
     */
    public function setResult($result): void
    {
        $this->result = $result;
    }

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

}