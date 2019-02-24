<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/7
 * Time: 8:41 PM
 */

namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;
use EasySwoole\Utility\Random;

class ProtocolPackage extends SplBean
{
    protected $nodeId;
    protected $packageId;
    protected $action;
    protected $arg;
    protected $fd;
    protected $rawData;
    protected $requestTime;

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
    public function getPackageId()
    {
        return $this->packageId;
    }

    /**
     * @param mixed $packageId
     */
    public function setPackageId($packageId): void
    {
        $this->packageId = $packageId;
    }

    /**
     * @return mixed
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param mixed $action
     */
    public function setAction($action): void
    {
        $this->action = $action;
    }

    /**
     * @return mixed
     */
    public function getArg()
    {
        return $this->arg;
    }

    /**
     * @param mixed $arg
     */
    public function setArg($arg): void
    {
        $this->arg = $arg;
    }

    /**
     * @return mixed
     */
    public function getFd()
    {
        return $this->fd;
    }

    /**
     * @param mixed $fd
     */
    public function setFd($fd): void
    {
        $this->fd = $fd;
    }

    /**
     * @return mixed
     */
    public function getRawData()
    {
        return $this->rawData;
    }

    /**
     * @param mixed $rawData
     */
    public function setRawData($rawData): void
    {
        $this->rawData = $rawData;
    }

    public static function pack(string $data):string
    {
        return pack('N', strlen($data)).$data;
    }

    public static function unpack(string $data):string
    {
        return substr($data,'4');
    }

    /**
     * @return mixed
     */
    public function getRequestTime()
    {
        return $this->requestTime;
    }

    /**
     * @param mixed $requestTime
     */
    public function setRequestTime($requestTime): void
    {
        $this->requestTime = $requestTime;
    }

    protected function initialize(): void
    {
        if(empty($this->packageId)){
            $this->packageId = Random::character(32);
        }
        if(empty($this->requestTime)){
            $this->requestTime = time();
        }
    }
}