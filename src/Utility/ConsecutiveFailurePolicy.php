<?php

namespace EasySwoole\Rpc\Utility;

use EasySwoole\Rpc\Exception\RpcException;

use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\Client\NodeFailureAction;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Server\ServiceNode;

final class ConsecutiveFailurePolicy implements NodeFailurePolicyInterface
{
    /** @var array<string, int> */
    private array $failures = [];

    public function __construct(private readonly int $threshold = 3)
    {
        if ($threshold < 1) {
            throw new RpcException('Failure threshold must be at least 1.');
        }
    }

    public function evaluate(ServiceNode $node, Response $response): NodeFailureAction
    {
        $key = $node->getServiceName() . "\0" . $node->getNodeId();

        if ($response->getNodeHealthStatus() === NodeHealthStatus::OFFLINE
            || $response->getStatus() === ResponseStatus::SERVICE_SHUTDOWN) {
            // 服务端明确声明不可用时无需累计，立即从注册中心移除该节点。
            unset($this->failures[$key]);
            return NodeFailureAction::OFFLINE;
        }

        $isFailure = $response->getNodeHealthStatus() === NodeHealthStatus::SUSPECT
            || in_array($response->getStatus(), [
                ResponseStatus::CONNECT_TIMEOUT,
                ResponseStatus::SERVER_TIMEOUT,
                ResponseStatus::TRANSPORT_ERROR,
                ResponseStatus::RESPONSE_DECODE_ERROR,
                ResponseStatus::PACKAGE_READ_TIMEOUT,
            ], true);
        if (!$isFailure) {
            // 任意健康响应都能证明节点可达，因此成功一次就重置连续失败计数。
            unset($this->failures[$key]);
            return NodeFailureAction::KEEP;
        }

        $this->failures[$key] = ($this->failures[$key] ?? 0) + 1;
        if ($this->failures[$key] >= $this->threshold) {
            // 只有连续失败达到阈值才下线，偶发网络抖动仅换节点重试。
            unset($this->failures[$key]);
            return NodeFailureAction::OFFLINE;
        }
        return NodeFailureAction::RETRY_ANOTHER;
    }
}
