<?php


namespace EasySwoole\Rpc;


class Command
{
    const SERVICE_CALL = 1;
    const SERVICE_STATUS = 2;
    protected $request;
    protected $command;

    /**
     * @return mixed
     */
    public function getRequest():?Request
    {
        return $this->request;
    }

    /**
     * @param mixed $request
     */
    public function setRequest($request): void
    {
        $this->request = $request;
    }

    /**
     * @return mixed
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param mixed $command
     */
    public function setCommand($command): void
    {
        $this->command = $command;
    }

}