<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;

final class ProtocolConfig
{
    public function __construct(public readonly int $maxPackageSize = 2097152)
    {
        if ($maxPackageSize < Protocol::HEADER_BYTES) {
            throw new RpcException('Maximum package size must be at least 4 bytes.');
        }
    }

    /** @return array<string, bool|string|int> */
    public function frameSettings(): array
    {
        return [
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => Protocol::HEADER_BYTES,
            'package_max_length' => $this->maxPackageSize,
        ];
    }

    public function payloadFits(string $payload): bool
    {
        return strlen($payload) <= $this->maxPackageSize - Protocol::HEADER_BYTES;
    }
}
