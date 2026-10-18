<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\Exception\RpcException;

final class SecurityConfig
{
    public function __construct(
        public readonly ?string $apiKey = null,
        public readonly int $timestampToleranceSeconds = 30,
    ) {
        if ($apiKey !== null && $apiKey === '') {
            throw new RpcException('RPC API key cannot be empty.');
        }
        if ($timestampToleranceSeconds < 1) {
            throw new RpcException('Timestamp tolerance must be at least 1 second.');
        }
    }

    public function enabled(): bool
    {
        return $this->apiKey !== null;
    }

}
