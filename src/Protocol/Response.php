<?php


namespace EasySwoole\Rpc\Protocol;


use EasySwoole\Spl\SplBean;
use EasySwoole\Utility\Random;

class Response extends SplBean
{
    const STATUS_OK = 0;
    const STATUS_NOT_AVAILABLE_NODE = 1001;
    const STATUS_CONNECT_TIMEOUT = 1002;
    const STATUS_SERVER_TIMEOUT = 1003;
    const STATUS_PACKAGE_READ_TIMEOUT = 2001;
    const STATUS_ILLEGAL_PACKAGE = 2002;
    const STATUS_SERVICE_SHUTDOWN = 3000;
    const STATUS_SERVICE_NOT_EXIST = 3001;
    const STATUS_MODULE_NOT_EXIST = 3002;
    const STATUS_ACTION_NOT_EXIST = 3003;
    const STATUS_SERVICE_ERROR = 3004;

    /** @var int $status */
    protected $status = self::STATUS_OK;
    /** @var mixed $result */
    protected $result;
    /** @var mixed $msg */
    protected $msg;
    /** @var string */
    protected $responseUUID;

    public static function status2msg(int $status)
    {
        switch ($status){
            case Response::STATUS_OK:{
                return 'ok';
            }
            case Response::STATUS_NOT_AVAILABLE_NODE:{
                return 'not available node';
            }
            case Response::STATUS_CONNECT_TIMEOUT:{
                return 'connect to service node timeout';
            }
            case Response::STATUS_SERVER_TIMEOUT:{
                return 'service response timeout';
            }
            case Response::STATUS_PACKAGE_READ_TIMEOUT:{
                return 'service read client request timeout';
            }
            case Response::STATUS_ILLEGAL_PACKAGE:{
                return 'service receive illegal client request';
            }
            case Response::STATUS_SERVICE_SHUTDOWN:{
                return 'service node is shutdown';
            }
            case Response::STATUS_SERVICE_NOT_EXIST:{
                return 'service not exist in server';
            }
            case Response::STATUS_MODULE_NOT_EXIST:{
                return 'module not exist in server';
            }
            case Response::STATUS_ACTION_NOT_EXIST:{
                return 'action not exist in server';
            }
            case Response::STATUS_SERVICE_ERROR:{
                return 'server error';
            }
            default:{
                return 'unknown error';
            }
        }
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMsg()
    {
        return $this->msg;
    }

    /**
     * @param $msg
     * @return $this
     */
    public function setMsg($msg): self
    {
        $this->msg = $msg;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param $result
     * @return $this
     */
    public function setResult($result): self
    {
        $this->result = $result;
        return $this;
    }

    protected function initialize(): void
    {
        if(empty($this->responseUUID)){
            $this->responseUUID = Random::makeUUIDV4();
        }
    }
}