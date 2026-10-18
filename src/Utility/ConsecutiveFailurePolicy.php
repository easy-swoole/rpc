<?php

namespace EasySwoole\Rpc\Utility;

use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\Client\NodeFailureAction;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
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

        if ($response->getStatus() === ResponseStatus::SERVICE_SHUTDOWN) {
            unset($this->failures[$key]);
            return NodeFailureAction::OFFLINE;
        }

        if (!in_array($response->getStatus(), [
            ResponseStatus::CONNECT_TIMEOUT,
            ResponseStatus::SERVER_TIMEOUT,
            ResponseStatus::TRANSPORT_ERROR,
            ResponseStatus::RESPONSE_DECODE_ERROR,
            ResponseStatus::PACKAGE_READ_TIMEOUT,
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
