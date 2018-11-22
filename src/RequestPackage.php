<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/7
 * Time: 8:57 PM
 */

namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;
use EasySwoole\Utility\Random;

class RequestPackage extends SplBean
{
    protected $nodeId;
    protected $packageId;
    protected $action;
    protected $signature;
    protected $packageTime;
    protected $arg = [];

    function generateSignature(string $key = null)
    {
        //对请求参数也签名
        $this->signature = md5($this->packageId.$key.$this->packageTime.implode('',$this->arg));
        return $this->signature;
    }

    function getSignature():string
    {
        return $this->signature;
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
    public function getPackageTime()
    {
        return $this->packageTime;
    }

    /**
     * @param mixed $packageTime
     */
    public function setPackageTime(int $packageTime = null): void
    {
        if($packageTime){
            $this->packageTime = $packageTime;
        }else{
            $this->packageTime = time();
        }
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
    public function setArg(array $arg): void
    {
        $this->arg = $arg;
    }

    protected function initialize(): void
    {
        if(empty($this->packageId)){
            $this->packageId = Random::character(8);
        }
    }

}