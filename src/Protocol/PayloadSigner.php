<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\SecurityConfig;

final class PayloadSigner
{
    public function __construct(private readonly SecurityConfig $config) {}

    public function sign(array $payload): ?string
    {
        if ($this->config->apiKey === null) {
            return null;
        }
        return hash_hmac('sha256', $this->canonicalJson($payload), $this->config->apiKey);
    }

    public function verify(array $payload, mixed $timestamp, mixed $signature): void
    {
        if ($this->config->apiKey === null) {
            return;
        }
        if (!is_int($timestamp) || abs(time() - $timestamp) > $this->config->timestampToleranceSeconds) {
            throw new RpcException('RPC signature timestamp is invalid or expired.');
        }
        $expected = $this->sign($payload);
        if (!is_string($signature) || $signature === '' || !hash_equals((string) $expected, $signature)) {
            throw new RpcException('Invalid RPC signature.');
        }
    }

    private function canonicalJson(array $payload): string
    {
        try {
            // 对象键顺序不应影响签名；列表顺序仍属于业务数据，不能排序。
            $normalized = json_decode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            return json_encode(self::sortRecursively($normalized), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RpcException('Unable to canonicalize RPC payload.', previous: $exception);
        }
    }

    private static function sortRecursively(mixed $value): mixed
    {
        if (!is_array($value)) { return $value; }
        foreach ($value as $key => $item) { $value[$key] = self::sortRecursively($item); }
        if (!array_is_list($value)) { ksort($value, SORT_STRING); }
        return $value;
    }
}
