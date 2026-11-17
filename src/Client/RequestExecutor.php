<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Server\ServiceNode;

final class RequestExecutor
{
    public function __construct(
        private readonly AbstractNodeManager $nodeManager,
        private readonly NodeSelectorInterface $nodeSelector,
        private readonly NodeFailurePolicyInterface $failurePolicy,
        private readonly TcpTransport $transport,
    ) {}

    public function execute(RequestContext $context, float $timeout): Response
    {
        $deadline = microtime(true) + $timeout;
        $service = explode('.', $context->getRequestPath(), 2)[0];
        $attempt = 0;
        $excludedNodeId = null;
        $lastResponse = null;
        do {
            // 有多个节点时排除刚失败的节点；只有一个节点时允许继续尝试同一节点。
            $node = $context->getServiceNode() ?? $this->resolveNode($service, $excludedNodeId);
            if ($node === false) {
                return $lastResponse ?? new Response(
                    '',
                    status: ResponseStatus::NODE_LOOKUP_FAILED,
                    msg: 'RPC node lookup failed.',
                );
            }
            if ($node === null) {
                // 已经发生过真实调用时保留最后一次失败，避免被“无可用节点”覆盖根因。
                return $lastResponse ?? new Response('', status: ResponseStatus::NOT_AVAILABLE_NODE);
            }
            $context->setServiceNode($node);
            $request = new Request($context->getRequestPath(), $context->getArg(), $context->getClientArg());
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                return $lastResponse ?? $this->deadlineExceededResponse($request);
            }
            $response = $this->validateResponse($request, $this->transport->send($node, $request, $remaining));
            $lastResponse = $response;
            // 失败策略只决定保留、换节点重试或下线；业务异常可以通过响应标记保持节点在线。
            $action = $this->failurePolicy->evaluate($node, $response);
            if ($action === NodeFailureAction::OFFLINE) {
                $this->nodeManager->offline($node);
            }
            if ($action === NodeFailureAction::KEEP) {
                return $response;
            }

            // 策略允许换节点时清除当前选择，下一次尝试仍使用同一个截止时间。
            $excludedNodeId = $node->getNodeId();
            $context->setServiceNode(null);
            $attempt++;
        } while ($attempt <= $context->getRetryTimes());
        return $response;
    }

    private function resolveNode(string $service, ?string $excludedNodeId): ServiceNode|false|null
    {
        $nodes = $this->nodeManager->getNodes($service);
        if ($nodes === false) {
            return false;
        }
        if ($nodes === []) {
            return null;
        }
        if ($excludedNodeId !== null && count($nodes) > 1) {
            $alternativeNodes = array_values(array_filter(
                $nodes,
                static fn (ServiceNode $node): bool => $node->getNodeId() !== $excludedNodeId,
            ));
            if ($alternativeNodes !== []) {
                $nodes = $alternativeNodes;
            }
        }
        return $this->nodeSelector->select($nodes);
    }

    private function validateResponse(Request $request, Response $response): Response
    {
        // 服务端必须原样回传 requestUUID，防止并发场景下错误响应被当前请求接收。
        // 包体损坏时服务端可能无法提取请求 UUID，短连接仍只承载当前请求。
        if ($response->getStatus() === ResponseStatus::ILLEGAL_PACKAGE && $response->getRequestUUID() === '') {
            return $response;
        }
        if ($response->getRequestUUID() === $request->getRequestUUID()) {
            return $response;
        }
        return new Response(
            $request->getRequestUUID(),
            status: ResponseStatus::RESPONSE_DECODE_ERROR,
            nodeHealthStatus: NodeHealthStatus::SUSPECT,
            msg: 'RPC response request UUID does not match the request UUID.',
        );
    }

    private function deadlineExceededResponse(Request $request): Response
    {
        return new Response(
            $request->getRequestUUID(),
            status: ResponseStatus::SERVER_TIMEOUT,
            nodeHealthStatus: NodeHealthStatus::SUSPECT,
            msg: 'RPC request deadline exceeded.',
        );
    }

}
