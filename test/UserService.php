<?php


namespace EasySwoole\Rpc\Test;


use EasySwoole\Rpc\AbstractService;

class UserService extends AbstractService
{
    public function serviceName(): string
    {
        return 'UserService';
    }


    public function register()
    {
        var_dump($this->request()->toArray());
        $this->response()->setResult([
            'account'=>'12345',
            'age'=>26
        ]);
    }


    public function login()
    {
        $this->response()->setResult([
            'account'=>'12345',
            'session'=>'xxxxxxxxxxxx'
        ]);
    }
}