<?php

namespace EasySwoole\Rpc\Protocol;

final class Protocol
{
    public static function pack(string $payload): string
    {
        return pack('N', strlen($payload)) . $payload;
    }

    public static function payloadLength(string $header): int
    {
        if (strlen($header) !== 4) {
            throw new \InvalidArgumentException('Protocol header must contain exactly 4 bytes.');
        }
        return unpack('Nlength', $header)['length'];
    }

    public static function unpack(string $packet): string
    {
        if (strlen($packet) < 4) {
            throw new \InvalidArgumentException('Incomplete protocol packet.');
        }
        $length = self::payloadLength(substr($packet, 0, 4));
        $payload = substr($packet, 4);
        if (strlen($payload) !== $length) {
            throw new \InvalidArgumentException('Protocol payload length does not match header.');
        }
        return $payload;
    }
}
