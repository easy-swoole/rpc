<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\Exception\RpcException;

final class DiscoveryMessage
{
    public const HEARTBEAT = 'heartbeat';
    public const OFFLINE = 'offline';

    public static function encode(
        string $action,
        ServiceNode $node,
        SecurityConfig $securityConfig = new SecurityConfig(),
        ?int $timestamp = null,
    ): string
    {
        $payload = ['action' => $action, 'node' => $node->jsonSerialize(), 'timestamp' => $timestamp ?? time()];
        $signer = new PayloadSigner($securityConfig);
        try {
            return json_encode([
                'payload' => $payload,
                'signature' => $signer->sign($payload),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RpcException('Unable to encode discovery message.', previous: $exception);
        }
    }

    public static function decode(
        string $packet,
        SecurityConfig $securityConfig = new SecurityConfig(),
    ): array
    {
        try {
            $envelope = json_decode($packet, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RpcException('Unable to decode discovery message.', previous: $exception);
        }
        if (!is_array($envelope['payload'] ?? null)) {
            throw new RpcException('Invalid discovery payload.');
        }
        $payload = $envelope['payload'];
        (new PayloadSigner($securityConfig))->verify(
            $payload,
            $payload['timestamp'] ?? null,
            $envelope['signature'] ?? null,
        );
        if (!in_array($payload['action'] ?? null, [self::HEARTBEAT, self::OFFLINE], true)
            || !is_array($payload['node'] ?? null)) {
            throw new RpcException('Invalid discovery message.');
        }
        $node = ServiceNode::fromArray($payload['node']);
        if ($node->getServiceName() === '') {
            throw new RpcException('Discovery node must contain a service name.');
        }
        return [$payload['action'], $node];
    }
}
