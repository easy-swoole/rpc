<?php


namespace EasySwoole\Rpc;


use EasySwoole\Spl\SplBean;

class Command extends SplBean
{
    const SERVICE_CALL = 1;
    const SERVICE_STATUS = 2;
    protected $request;
    protected $command;


    protected function setClassMapping(): array
    {
        return [
            'request'=>Request::class
        ];
    }

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

    public function __toString()
    {
        $array = $this->toArray();
        if($array['request'] instanceof Request){
            $array['request'] = $array['request']->toArray();
        }
        return json_encode($this->jsonSerialize(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

}