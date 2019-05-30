<?php


namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class Response extends SplBean
{
    const STATUS_OK = 0;
    const STATUS_NODES_EMPTY = 1001;
    const STATUS_CONNECT_TIMEOUT =  1002;
    const STATUS_SERVER_TIMEOUT = 1003;
    const STATUS_SERVICE_NOT_EXIST = 2001;
    const STATUS_SERVICE_ACTION_NOT_FOUND = 2002;
    const STATUS_SERVICE_ERROR = 2003;
    const STATUS_ILLEGAL_PACKAGE = 2004;

    protected $result;
    protected $status = self::STATUS_OK;
    protected $nodeId;
    protected $msg;

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
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * @param mixed $nodeId
     */
    public function setNodeId($nodeId): void
    {
        $this->nodeId = $nodeId;
    }

    /**
     * @return mixed
     */
    public function getMsg()
    {
        return $this->msg;
    }

    /**
     * @param mixed $msg
     */
    public function setMsg($msg): void
    {
        $this->msg = $msg;
    }
}