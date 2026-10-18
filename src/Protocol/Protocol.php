<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;

final class Protocol
{
    public static function pack(string $payload): string
    {
        return pack('N', strlen($payload)) . $payload;
    }

    public static function payloadLength(string $header): int
    {
        if (strlen($header) !== 4) {
            throw new RpcException('Protocol header must contain exactly 4 bytes.');
        }
        return unpack('Nlength', $header)['length'];
    }

    public static function unpack(string $packet): string
    {
        if (strlen($packet) < 4) {
            throw new RpcException('Incomplete protocol packet.');
        }
        $length = self::payloadLength(substr($packet, 0, 4));
        $payload = substr($packet, 4);
        if (strlen($payload) !== $length) {
            throw new RpcException('Protocol payload length does not match header.');
        }
        return $payload;
    }
}
