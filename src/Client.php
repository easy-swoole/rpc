<?php


namespace EasySwoole\Rpc;


use EasySwoole\Rpc\NodeManager\NodeManagerInterface;

class Client
{
    protected $nodeManager;
    function __construct(NodeManagerInterface $manager)
    {
        $this->nodeManager = $manager;
    }
}