<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;

final class RequestBatch
{
    /** @var list<RequestContext> */
    private array $calls = [];
    private bool $executing = false;

    public function __construct(private readonly RequestExecutor $executor) {}

    public function addCall(string $path): RequestContext
    {
        if ($this->executing) {
            throw new RpcException('Calls cannot be added while an RPC request batch is executing.');
        }
        Request::validatePath($path);
        return $this->calls[] = new RequestContext($path);
    }

    /** @return list<Response> */
    public function exec(float $timeout = 3.0): array
    {
        if ($this->executing) {
            throw new RpcException('The same RPC request batch cannot be executed concurrently.');
        }

        $this->executing = true;
        $responses = [];
        $errors = [];
        $calls = $this->calls;
        $this->calls = [];
        $execute = function () use (&$responses, &$errors, $calls, $timeout): void {
            $waitGroup = new \Swoole\Coroutine\WaitGroup();
            foreach ($calls as $index => $context) {
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
            $this->executing = false;
        }
    }
}
