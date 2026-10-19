<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;

final class Client
{
    /** @var list<RequestContext> */
    private array $requests = [];
    private readonly RequestExecutor $executor;
    public function __construct(
        private readonly AbstractNodeManager $nodeManager,
        private readonly NodeSelectorInterface $nodeSelector,
        private readonly NodeFailurePolicyInterface $failurePolicy,
        TcpTransport $transport,
    ) {
        $this->executor = new RequestExecutor($this->nodeManager, $this->nodeSelector, $this->failurePolicy, $transport);
    }
    public function addRequest(string $path): RequestContext
    {
        Request::validatePath($path);
        return $this->requests[] = new RequestContext($path);
    }

    /** @return list<Response> */
    public function exec(float $timeout = 3.0): array
    {
        $responses = [];
        $errors = [];
        $requests = $this->requests;
        $execute = function () use (&$responses, &$errors, $requests, $timeout): void {
            $waitGroup = new \Swoole\Coroutine\WaitGroup();
            foreach ($requests as $index => $context) {
                $waitGroup->add();
                \Swoole\Coroutine::create(function () use (&$responses, &$errors, $index, $context, $timeout, $waitGroup): void {
                    try {
                        $response = $this->executor->execute($context, $timeout);
                        $context->notify($response);
                        $responses[$index] = $response;
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

}
