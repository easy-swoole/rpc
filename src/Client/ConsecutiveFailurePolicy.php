<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Server\ServiceNode;

final class ConsecutiveFailurePolicy implements NodeFailurePolicyInterface
{
    /** @var array<string, int> */
    private array $failures = [];

    public function __construct(private readonly int $threshold = 3)
    {
        if ($threshold < 1) {
            throw new \InvalidArgumentException('Failure threshold must be at least 1.');
        }
    }

    public function evaluate(ServiceNode $node, Response $response): NodeFailureAction
    {
        $key = $node->getServiceName() . "\0" . $node->getNodeId();

        if ($response->getStatus() === Response::STATUS_SERVICE_SHUTDOWN) {
            unset($this->failures[$key]);
            return NodeFailureAction::OFFLINE;
        }

        if (!in_array($response->getStatus(), [
            Response::STATUS_CONNECT_TIMEOUT,
            Response::STATUS_SERVER_TIMEOUT,
            Response::STATUS_PACKAGE_READ_TIMEOUT,
        ], true)) {
            // A response from the service proves that the node is reachable.
            unset($this->failures[$key]);
            return NodeFailureAction::KEEP;
        }

        $this->failures[$key] = ($this->failures[$key] ?? 0) + 1;
        if ($this->failures[$key] >= $this->threshold) {
            unset($this->failures[$key]);
            return NodeFailureAction::OFFLINE;
        }
        return NodeFailureAction::RETRY_ANOTHER;
    }
}
