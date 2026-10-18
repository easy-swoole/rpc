<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\TransportInterface;
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;

final class Client
{
    /** @var list<RequestContext> */
    private array $requests = [];
    private ?TransportInterface $transport = null;

    public function __construct(
        private readonly AbstractNodeManager $nodeManager,
        private readonly NodeSelectorInterface $nodeSelector,
        private readonly NodeFailurePolicyInterface $failurePolicy,
    ) {}
    public function setTransport(TransportInterface $transport): self { $this->transport = $transport; return $this; }
    public function addRequest(string $path): RequestContext
    {
        return $this->requests[] = new RequestContext($path);
    }

    /** @return list<Response> */
    public function exec(float $timeout = 3.0): array
    {
        if ($this->transport === null) {
            throw new \LogicException('Please set a client transport before exec().');
        }
        $responses = [];
        $execute = function () use (&$responses, $timeout): void {
            $waitGroup = new \Swoole\Coroutine\WaitGroup();
            foreach ($this->requests as $index => $context) {
                $waitGroup->add();
                \Swoole\Coroutine::create(function () use (&$responses, $index, $context, $timeout, $waitGroup): void {
                    try {
                        $responses[$index] = $this->executeOne($context, $timeout);
                    } finally {
                        $waitGroup->done();
                    }
                });
            }
            $waitGroup->wait();
        };
        if (\Swoole\Coroutine::getCid() >= 0) {
            $execute();
        } else {
            \Swoole\Coroutine\run($execute);
        }
        ksort($responses);
        $this->requests = [];
        return array_values($responses);
    }

    private function executeOne(RequestContext $context, float $timeout): Response
    {
        $service = explode('.', $context->getRequestPath(), 2)[0];
        $attempt = 0;
        do {
            $node = $context->getServiceNode()
                ?? $this->nodeSelector->select($this->nodeManager->getNodes($service));
            if ($node === null) {
                $response = (new Response(bin2hex(random_bytes(16))))->setStatus(Response::STATUS_NOT_AVAILABLE_NODE);
                break;
            }
            $context->setServiceNode($node);
            $request = new Request($context->getRequestPath(), $context->getArg(), $context->getClientArg());
            try {
                $response = $this->transport->send($node, $request, $timeout);
            } catch (\Throwable $throwable) {
                $response = (new Response($request->getRequestUUID()))
                    ->setStatus(Response::STATUS_CONNECT_TIMEOUT)->setMsg($throwable->getMessage());
            }
            $failureAction = $this->failurePolicy->evaluate($node, $response);
            if ($failureAction === NodeFailureAction::OFFLINE) {
                $this->nodeManager->offline($node);
            }
            if ($failureAction !== NodeFailureAction::KEEP) {
                $context->setServiceNode(null);
            }
            $attempt++;
        } while (!$response->isSuccess() && $attempt <= $context->getRetryTimes());
        $context->notify($response);
        return $response;
    }
}
