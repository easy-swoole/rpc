<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;

final class ProtocolConfig
{
    public function __construct(public readonly int $maxPackageSize = 2097152)
    {
        if ($maxPackageSize < 4) {
            throw new RpcException('Maximum package size must be at least 4 bytes.');
        }
    }
}
