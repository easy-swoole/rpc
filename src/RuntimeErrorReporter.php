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
            // Runtime error reporting must never alter the RPC lifecycle.
        }
    }
}
