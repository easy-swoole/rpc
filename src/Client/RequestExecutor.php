<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\RuntimeErrorReporter;
use EasySwoole\Rpc\Server\ServiceNode;

final class RequestExecutor
{
    public function __construct(
        private readonly AbstractNodeManager $nodeManager,
        private readonly NodeSelectorInterface $nodeSelector,
        private readonly NodeFailurePolicyInterface $failurePolicy,
        private readonly TcpTransport $transport,
        private readonly RuntimeErrorReporter $runtimeErrorReporter = new RuntimeErrorReporter(),
    ) {}

    public function execute(RequestContext $context, float $timeout): Response
    {
        $service = explode('.', $context->getRequestPath(), 2)[0];
        $attempt = 0;
        do {
            $shouldRetry = false;
            // 重试时会清空 context 中的旧节点，再通过 NodeSelector 选择其他可用节点。
            $node = $context->getServiceNode() ?? $this->resolveNode($service);
            if ($node === null) {
                return $this->unavailableResponse();
            }
            $context->setServiceNode($node);
            $request = new Request($context->getRequestPath(), $context->getArg(), $context->getClientArg());
            $response = $this->validateResponse($request, $this->sendRequest($node, $request, $timeout));
            // 失败策略只决定保留、换节点重试或下线；业务异常可以通过响应标记保持节点在线。
            $action = $this->failurePolicy->evaluate($node, $response);
            if ($action === NodeFailureAction::OFFLINE) {
                $this->offlineNode($node);
            }
            if ($action !== NodeFailureAction::KEEP) {
                $context->setServiceNode(null);
                $shouldRetry = true;
            }
            $attempt++;
        } while ($shouldRetry && $attempt <= $context->getRetryTimes());
        return $response;
    }

    private function resolveNode(string $service): ?ServiceNode
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
            $this->runtimeErrorReporter->report(sprintf(
                'RPC node query failed for service "%s"%s.',
                $service,
                $queryError === null ? '' : ': ' . $queryError,
            ));
            return null;
        }
        return $this->nodeSelector->select($nodes);
    }

    private function sendRequest(ServiceNode $node, Request $request, float $timeout): Response
    {
        try {
            return $this->transport->send($node, $request, $timeout);
        } catch (\Throwable $throwable) {
            $this->runtimeErrorReporter->report(sprintf(
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
        $this->runtimeErrorReporter->report(sprintf(
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
}
