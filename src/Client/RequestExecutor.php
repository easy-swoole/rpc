<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;

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
        $service = explode('.', $context->getRequestPath(), 2)[0];
        $attempt = 0;
        do {
            $shouldRetry = false;
            $node = $context->getServiceNode() ?? $this->nodeSelector->select($this->nodeManager->getNodes($service));
            if ($node === null) { return (new Response(bin2hex(random_bytes(16))))->setStatus(ResponseStatus::NOT_AVAILABLE_NODE); }
            $context->setServiceNode($node);
            $request = new Request($context->getRequestPath(), $context->getArg(), $context->getClientArg());
            try {
                $response = $this->transport->send($node, $request, $timeout);
            } catch (TransportException $throwable) {
                $response = (new Response($request->getRequestUUID()))->setStatus($throwable->getResponseStatus())->setNodeHealthStatus(NodeHealthStatus::SUSPECT)->setMsg($throwable->getMessage());
            } catch (\Throwable $throwable) {
                $response = (new Response($request->getRequestUUID()))->setStatus(ResponseStatus::TRANSPORT_ERROR)->setNodeHealthStatus(NodeHealthStatus::SUSPECT)->setMsg($throwable->getMessage());
            }
            if ($response->getResponseUUID() !== $request->getRequestUUID()) {
                $response = (new Response($request->getRequestUUID()))->setStatus(ResponseStatus::RESPONSE_DECODE_ERROR)->setNodeHealthStatus(NodeHealthStatus::SUSPECT)->setMsg('RPC response UUID does not match request UUID.');
            }
            $action = $this->failurePolicy->evaluate($node, $response);
            if ($action === NodeFailureAction::OFFLINE) { $this->nodeManager->offline($node); }
            if ($action !== NodeFailureAction::KEEP) { $context->setServiceNode(null); $shouldRetry = true; }
            $attempt++;
        } while ($shouldRetry && $attempt <= $context->getRetryTimes());
        return $response;
    }
}
