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
    private mixed $onRuntimeError;

    public function __construct(
        private readonly AbstractNodeManager $nodeManager,
        private readonly NodeSelectorInterface $nodeSelector,
        private readonly NodeFailurePolicyInterface $failurePolicy,
        private readonly TcpTransport $transport,
        ?callable $onRuntimeError = null,
    ) {
        $this->onRuntimeError = $onRuntimeError;
    }

    public function setOnRuntimeError(callable $callback): void
    {
        $this->onRuntimeError = $callback;
    }

    public function execute(RequestContext $context, float $timeout): Response
    {
        $service = explode('.', $context->getRequestPath(), 2)[0];
        $attempt = 0;
        $excludedNodeId = null;
        $lastResponse = null;
        do {
            $shouldRetry = false;
            // 有多个节点时排除刚失败的节点；只有一个节点时允许继续尝试同一节点。
            $node = $context->getServiceNode() ?? $this->resolveNode($service, $excludedNodeId);
            if ($node === null) {
                // 已经发生过真实调用时保留最后一次失败，避免被“无可用节点”覆盖根因。
                return $lastResponse ?? $this->unavailableResponse();
            }
            $context->setServiceNode($node);
            $request = new Request($context->getRequestPath(), $context->getArg(), $context->getClientArg());
            $response = $this->validateResponse($request, $this->sendRequest($node, $request, $timeout));
            $lastResponse = $response;
            // 失败策略只决定保留、换节点重试或下线；业务异常可以通过响应标记保持节点在线。
            $action = $this->failurePolicy->evaluate($node, $response);
            if ($action === NodeFailureAction::OFFLINE) {
                $this->offlineNode($node);
            }
            if ($action !== NodeFailureAction::KEEP) {
                $excludedNodeId = $node->getNodeId();
                $context->setServiceNode(null);
                $shouldRetry = true;
            }
            $attempt++;
        } while ($shouldRetry && $attempt <= $context->getRetryTimes());
        return $response;
    }

    private function resolveNode(string $service, ?string $excludedNodeId): ?ServiceNode
    {
        $queryError = null;
        try {
            $nodes = $this->nodeManager->getNodes($service);
        } catch (\Throwable $throwable) {
            $nodes = false;
            $queryError = $throwable->getMessage();
        }
        if ($nodes === false) {
            // false 表示注册中心查询失败；空数组则表示查询成功但当前没有节点。
            $this->report(sprintf(
                'RPC node query failed for service "%s"%s.',
                $service,
                $queryError === null ? '' : ': ' . $queryError,
            ));
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

    private function sendRequest(ServiceNode $node, Request $request, float $timeout): Response
    {
        try {
            return $this->transport->send($node, $request, $timeout);
        } catch (\Throwable $throwable) {
            $this->report(sprintf(
                'Unexpected RPC transport failure for service "%s", node "%s": %s',
                $node->getServiceName(),
                $node->getNodeId(),
                $throwable->getMessage(),
            ));
            return (new Response($request->getRequestUUID()))
                ->setStatus(ResponseStatus::TRANSPORT_ERROR)
                ->setNodeHealthStatus(NodeHealthStatus::SUSPECT)
                ->setMsg('Unexpected RPC transport failure.');
        }
    }

    private function validateResponse(Request $request, Response $response): Response
    {
        // 服务端必须原样回传 requestUUID，防止并发场景下错误响应被当前请求接收。
        if ($response->getRequestUUID() === $request->getRequestUUID()) {
            return $response;
        }
        return (new Response($request->getRequestUUID()))
            ->setStatus(ResponseStatus::RESPONSE_DECODE_ERROR)
            ->setNodeHealthStatus(NodeHealthStatus::SUSPECT)
            ->setMsg('RPC response request UUID does not match the request UUID.');
    }

    private function offlineNode(ServiceNode $node): void
    {
        try {
            if ($this->nodeManager->offline($node)) {
                return;
            }
            $message = '';
        } catch (\Throwable $throwable) {
            $message = ': ' . $throwable->getMessage();
        }
        $this->report(sprintf(
            'RPC node offline failed for service "%s", node "%s"%s.',
            $node->getServiceName(),
            $node->getNodeId(),
            $message,
        ));
    }

    private function unavailableResponse(): Response
    {
        // 尚未生成或发出 TCP 请求，因此这里不能伪造一个随机 requestUUID。
        return (new Response(''))
            ->setStatus(ResponseStatus::NOT_AVAILABLE_NODE);
    }

    private function report(string $message): void
    {
        if (!is_callable($this->onRuntimeError)) {
            return;
        }
        try {
            ($this->onRuntimeError)($message);
        } catch (\Throwable) {
            // 运行日志回调不能反向破坏客户端请求生命周期。
        }
    }
}
