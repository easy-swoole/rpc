<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
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
        private readonly SecurityConfig $securityConfig = new SecurityConfig(),
    ) {}
    public function setTransport(TransportInterface $transport): self { $this->transport = $transport; return $this; }
    public function addRequest(string $path): RequestContext
    {
        Request::validatePath($path);
        return $this->requests[] = new RequestContext($path);
    }

    /** @return list<Response> */
    public function exec(float $timeout = 3.0): array
    {
        if ($this->transport === null) {
            throw new \LogicException('Please set a client transport before exec().');
        }
        $responses = [];
        $errors = [];
        $requests = $this->requests;
        $execute = function () use (&$responses, &$errors, $requests, $timeout): void {
            $waitGroup = new \Swoole\Coroutine\WaitGroup();
            foreach ($requests as $index => $context) {
                $waitGroup->add();
                \Swoole\Coroutine::create(function () use (&$responses, &$errors, $index, $context, $timeout, $waitGroup): void {
                    try {
                        $responses[$index] = $this->executeOne($context, $timeout);
                    } catch (\Throwable $throwable) {
                        $errors[$index] = $throwable;
                    } finally {
                        $waitGroup->done();
                    }
                });
            }
            $waitGroup->wait();
        };
        try {
            if (\Swoole\Coroutine::getCid() >= 0) {
                $execute();
            } else {
                \Swoole\Coroutine\run($execute);
            }
            if ($errors !== []) {
                ksort($errors);
                throw reset($errors);
            }
            ksort($responses);
            return array_values($responses);
        } finally {
            $this->requests = [];
        }
    }

    private function executeOne(RequestContext $context, float $timeout): Response
    {
        $service = explode('.', $context->getRequestPath(), 2)[0];
        $attempt = 0;
        do {
            $shouldRetry = false;
            $node = $context->getServiceNode()
                ?? $this->nodeSelector->select($this->nodeManager->getNodes($service));
            if ($node === null) {
                $response = (new Response(bin2hex(random_bytes(16))))->setStatus(ResponseStatus::NOT_AVAILABLE_NODE);
                break;
            }
            $context->setServiceNode($node);
            $request = (new Request($context->getRequestPath(), $context->getArg(), $context->getClientArg()))
                ->sign($this->securityConfig);
            try {
                $response = $this->transport->send($node, $request, $timeout);
                try {
                    $response->verify($this->securityConfig);
                    if ($response->getResponseUUID() !== $request->getRequestUUID()) {
                        throw new \UnexpectedValueException('RPC response UUID does not match request UUID.');
                    }
                } catch (\Throwable $throwable) {
                    throw new TransportException(
                        ResponseStatus::RESPONSE_DECODE_ERROR,
                        $throwable->getMessage(),
                        previous: $throwable,
                    );
                }
            } catch (TransportException $throwable) {
                $response = (new Response($request->getRequestUUID()))
                    ->setStatus($throwable->getResponseStatus())->setMsg($throwable->getMessage());
            } catch (\Throwable $throwable) {
                $response = (new Response($request->getRequestUUID()))
                    ->setStatus(ResponseStatus::TRANSPORT_ERROR)->setMsg($throwable->getMessage());
            }
            $failureAction = $this->failurePolicy->evaluate($node, $response);
            if ($failureAction === NodeFailureAction::OFFLINE) {
                $this->nodeManager->offline($node);
            }
            if ($failureAction !== NodeFailureAction::KEEP) {
                $context->setServiceNode(null);
                $shouldRetry = true;
            }
            $attempt++;
        } while ($shouldRetry && $attempt <= $context->getRetryTimes());
        $context->notify($response);
        return $response;
    }
}
