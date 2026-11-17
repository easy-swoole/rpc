<?php

namespace EasySwoole\Rpc\Protocol;

final class RequestFrame
{
    private const VERSION = "RPC\x01";
    private const SIGNED_BYTES = 48;
    public const HEADER_BYTES = 80;

    public static function pack(string $payload, PayloadSigner $signer): string
    {
        $header = self::VERSION . pack('NJ', strlen($payload), time()) . hash('sha256', $payload, true);
        return $header . $signer->signHeader($header) . $payload;
    }

    /** @return array{length: int, hash: string}|null */
    public static function inspect(string $header, int $maxPackageSize, PayloadSigner $signer): ?array
    {
        if (strlen($header) !== self::HEADER_BYTES || substr($header, 0, 4) !== self::VERSION) {
            return null;
        }
        $fields = unpack('Nlength/Jtimestamp', $header, 4);
        if ($fields['length'] < 1 || $fields['length'] > $maxPackageSize - self::HEADER_BYTES) {
            return null;
        }
        if (!$signer->acceptsHeader(
            substr($header, 0, self::SIGNED_BYTES),
            $fields['timestamp'],
            substr($header, self::SIGNED_BYTES),
        )) {
            return null;
        }
        return ['length' => $fields['length'], 'hash' => substr($header, 16, 32)];
    }
}
