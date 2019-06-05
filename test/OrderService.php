<?php


namespace EasySwoole\Rpc\Test;


use EasySwoole\Component\TableManager;
use EasySwoole\Rpc\AbstractService;
use EasySwoole\Rpc\ServiceNode;

class OrderService extends AbstractService
{

    public function serviceName(): string
    {
        return 'OrderService';
    }

    function add()
    {
        $this->response()->setResult([
            'orderNum' => 123456789,
            'balance' => 11.5,
            'subject' => "订单商品"
        ]);
    }

    public function cancel()
    {
        $this->response()->setResult([
            'orderNum' => 123456789,
            'balance' => 11.5,
            'subject' => "订单商品",
            'isCancel' => 1
        ]);
    }

    public function info()
    {
        $this->response()->setResult([
            'orderNum' => 123456789,
            'balance' => 11.5,
            'subject' => "订单商品",
            'isCancel' => 1
        ]);
    }
}