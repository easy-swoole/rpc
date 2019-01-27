<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-27
 * Time: 19:07
 */

namespace EasySwoole\Rpc\Task;


use EasySwoole\Rpc\Task;

class Service
{
    protected $serviceName;
    protected $version = '1.0.0';
    protected $task = [];

    function __construct(string $serviceName,?string $version = null)
    {
        $this->serviceName = $serviceName;
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * @param string $serviceName
     */
    public function setServiceName(string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @return string|null
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * @param string|null $version
     */
    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    public function createTask():Task
    {
        $task = new Task();
        $this->task[$task->getTaskId()] = $task;
        return $task;
    }

    public function getTask():array
    {
        return $this->task;
    }
}