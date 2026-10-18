<?php

namespace EasySwoole\Rpc;

final class SecurityConfig
{
    public function __construct(
        public readonly ?string $apiKey = null,
        public readonly int $timestampToleranceSeconds = 30,
    ) {
        if ($apiKey !== null && $apiKey === '') {
            throw new \InvalidArgumentException('RPC API key cannot be empty.');
        }
        if ($timestampToleranceSeconds < 1) {
            throw new \InvalidArgumentException('Timestamp tolerance must be at least 1 second.');
        }
    }

    public function enabled(): bool
    {
        return $this->apiKey !== null;
    }

    public function sign(array $payload): ?string
    {
        if ($this->apiKey === null) {
            return null;
        }
        return hash_hmac(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $this->apiKey,
        );
    }

    public function verify(array $payload, mixed $timestamp, mixed $signature): void
    {
        if ($this->apiKey === null) {
            return;
        }
        if (!is_int($timestamp) || abs(time() - $timestamp) > $this->timestampToleranceSeconds) {
            throw new \InvalidArgumentException('RPC signature timestamp is invalid or expired.');
        }
        $expected = $this->sign($payload);
        if (!is_string($signature) || $signature === '' || !hash_equals((string) $expected, $signature)) {
            throw new \InvalidArgumentException('Invalid RPC signature.');
        }
    }
}
