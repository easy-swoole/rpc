<?php

namespace EasySwoole\Rpc;

final class RuntimeErrorReporter
{
    private ?\Closure $callback = null;

    public function setHandler(?callable $callback): void
    {
        $this->callback = $callback === null ? null : \Closure::fromCallable($callback);
    }

    public function report(string $message): void
    {
        if ($this->callback === null) {
            return;
        }
        try {
            ($this->callback)($message);
        } catch (\Throwable) {
            // 运行日志回调必须与 RPC 生命周期隔离，回调异常不能终止心跳或请求。
        }
    }
}
