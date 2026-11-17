<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

final class RequestBatch
{
    /** @var array<string, RequestContext> */
    private array $calls = [];
    private bool $started = false;

    public function __construct(
        private readonly RequestExecutor $executor,
        private readonly mixed $onException = null,
    ) {}

    public function addCall(string $path): RequestContext
    {
        if ($this->started) {
            throw new RpcException('Calls cannot be added after an RPC request batch has started executing.');
        }
        Request::validatePath($path);
        if (isset($this->calls[$path])) {
            throw new RpcException(sprintf('RPC request path "%s" is already in this batch.', $path));
        }
        return $this->calls[$path] = new RequestContext($path);
    }

    /** @return array<string, Response> */
    public function exec(float $timeout = 3.0): array
    {
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new RpcException('RPC timeout must be greater than zero.');
        }
        if (Coroutine::getCid() < 0) {
            throw new RpcException('RPC request batch must be executed inside a Swoole coroutine.');
        }
        if ($this->started) {
            throw new RpcException('The same RPC request batch can only be executed once.');
        }

        $this->started = true;
        $responses = [];
        // 执行前先取走队列，即使回调抛出异常，旧调用也不会在下次 exec() 中重复执行。
        $calls = $this->calls;
        $this->calls = [];
        $waitGroup = new WaitGroup();
        foreach ($calls as $path => $context) {
            // 每个调用独占一个协程；响应以完整调用路径记录。
            $waitGroup->add();
            Coroutine::create(function () use (&$responses, $path, $context, $timeout, $waitGroup): void {
                try {
                    $response = $this->executor->execute($context, $timeout);
                    // Response 已经完成，Action 回调异常不应导致该响应从 Batch 结果中丢失。
                    $responses[$path] = $response;
                    $context->notify($response);
                } catch (\Throwable $throwable) {
                    if ($this->onException) {
                        call_user_func($this->onException, $throwable);
                    } else {
                        // 未配置异常处理器时直接暴露错误，由进程管理与运维体系接管。
                        throw $throwable;
                    }
                } finally {
                    $waitGroup->done();
                }
            });
        }
        $waitGroup->wait();
        $orderedResponses = [];
        foreach (array_keys($calls) as $path) {
            if (isset($responses[$path])) {
                $orderedResponses[$path] = $responses[$path];
            }
        }
        return $orderedResponses;
    }
}
