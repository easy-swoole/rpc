<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;

interface TransportInterface
{
    public function send(ServiceNode $node, Request $request, float $timeout): Response;
}
