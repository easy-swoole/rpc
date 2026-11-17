<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;

final class Protocol
{
    public const HEADER_BYTES = 4;

    public static function pack(string $payload): string
    {
        // 固定使用 4 字节网络序无符号整数描述后续 JSON 载荷长度。
        return pack('N', strlen($payload)) . $payload;
    }

    public static function payloadLength(string $header): int
    {
        if (strlen($header) !== self::HEADER_BYTES) {
            throw new RpcException('Protocol header must contain exactly 4 bytes.');
        }
        return unpack('Nlength', $header)['length'];
    }

    public static function unpack(string $packet): string
    {
        if (strlen($packet) < self::HEADER_BYTES) {
            throw new RpcException('Incomplete protocol packet.');
        }
        $length = self::payloadLength(substr($packet, 0, self::HEADER_BYTES));
        $payload = substr($packet, self::HEADER_BYTES);
        if (strlen($payload) !== $length) {
            throw new RpcException('Protocol payload length does not match header.');
        }
        return $payload;
    }
}
