<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Client\NodeFailureAction;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Server\ServiceNode;

interface NodeFailurePolicyInterface
{
    public function evaluate(ServiceNode $node, Response $response): NodeFailureAction;
}
