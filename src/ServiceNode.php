<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/12
 * Time: 10:33 PM
 */

namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class ServiceNode extends SplBean
{
    protected $serviceIp;
    protected $servicePort = 9600;
    protected $serviceVersion = '1.0.0';
    protected $serviceName;
    protected $nodeExpire = null;
    protected $nodeId;

    /**
     * @return mixed
     */
    public function getServiceIp()
    {
        return $this->serviceIp;
    }

    /**
     * 设置服务ip
     * @param mixed $serviceIp
     */
    public function setServiceIp($serviceIp): void
    {
        $this->serviceIp = $serviceIp;
    }

    /**
     * @return mixed
     */
    public function getServicePort()
    {
        return $this->servicePort;
    }

    /**
     * 设置服务端口
     * @param mixed $servicePort
     */
    public function setServicePort($servicePort): void
    {
        $this->servicePort = $servicePort;
    }

    /**
     * @return mixed
     */
    public function getServiceVersion()
    {
        return $this->serviceVersion;
    }

    /**
     * 设置服务版本
     * @param mixed $serviceVersion
     */
    public function setServiceVersion($serviceVersion): void
    {
        $this->serviceVersion = $serviceVersion;
    }

    /**
     * @return mixed
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * 设置服务版本
     * @param mixed $serviceName
     */
    public function setServiceName($serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @return mixed
     */
    public function getNodeExpire()
    {
        return $this->nodeExpire;
    }

    /**
     * 设置节点过期时间
     * @param mixed $nodeExpire
     */
    public function setNodeExpire($nodeExpire): void
    {
        $this->nodeExpire = $nodeExpire;
    }

    /**
     * @return mixed
     */
    public function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * 设置节点ID
     * @param mixed $nodeId
     */
    public function setNodeId($nodeId): void
    {
        $this->nodeId = $nodeId;
    }

    /**
     * 默认过期时间当前时间+15s
     */
    protected function initialize(): void
    {
        if ($this->nodeExpire === null) {
            $this->nodeExpire = time() + 15;
        }
    }

}