<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

final class RequestBatch
{
    /** @var list<RequestContext> */
    private array $calls = [];
    private bool $executing = false;
    private bool $executed = false;

    public function __construct(
        private readonly RequestExecutor $executor,
        private readonly mixed $onException = null,
    ) {}

    public function addCall(string $path): RequestContext
    {
        if ($this->executed) {
            throw new RpcException('Calls cannot be added after an RPC request batch has started executing.');
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
        if (Coroutine::getCid() < 0) {
            throw new RpcException('RPC request batch must be executed inside a Swoole coroutine.');
        }
        if ($this->executing) {
            throw new RpcException('The same RPC request batch cannot be executed concurrently.');
        }
        if ($this->executed) {
            throw new RpcException('The same RPC request batch can only be executed once.');
        }

        $this->executing = true;
        $this->executed = true;
        $responses = [];
        // 执行前先取走队列，即使回调抛出异常，旧调用也不会在下次 exec() 中重复执行。
        $calls = $this->calls;
        $this->calls = [];
        $waitGroup = new WaitGroup();
        foreach ($calls as $index => $context) {
            // 每个调用独占一个协程；最终按原始下标恢复调用方添加时的顺序。
            $waitGroup->add();
            Coroutine::create(function () use (&$responses, $index, $context, $timeout, $waitGroup): void {
                try {
                    $response = $this->executor->execute($context, $timeout);
                    // Response 已经完成，Action 回调异常不应导致该响应从 Batch 结果中丢失。
                    $responses[$index] = $response;
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
        try {
            $waitGroup->wait();
        } finally {
            $this->executing = false;
        }
        ksort($responses);
        return array_values($responses);
    }
}
