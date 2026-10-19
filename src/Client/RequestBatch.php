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

    public function __construct(
        private readonly RequestExecutor $executor,
        private readonly mixed $onException = null,
    ) {}

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
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new RpcException('RPC timeout must be greater than zero.');
        }
        if ($this->executing) {
            throw new RpcException('The same RPC request batch cannot be executed concurrently.');
        }

        $this->executing = true;
        $responses = [];
        $errors = [];
        // 执行前先取走队列，即使回调抛出异常，旧调用也不会在下次 exec() 中重复执行。
        $calls = $this->calls;
        $this->calls = [];
        $execute = function () use (&$responses, &$errors, $calls, $timeout): void {
            $waitGroup = new \Swoole\Coroutine\WaitGroup();
            foreach ($calls as $index => $context) {
                // 每个调用独占一个协程；最终按原始下标恢复调用方添加时的顺序。
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
                // 等待全部协程收尾后再抛出，避免其中一个异常导致其他调用被遗留。
                ksort($errors);
                $throwable = reset($errors);
                if (is_callable($this->onException)) {
                    try {
                        ($this->onException)($throwable);
                    } catch (\Throwable) {
                        // 异常观察回调不能覆盖即将抛给调用方的原始异常。
                    }
                }
                throw $throwable;
            }
            ksort($responses);
            return array_values($responses);
        } finally {
            $this->executing = false;
        }
    }
}
