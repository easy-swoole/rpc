<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\SecurityConfig;

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
        return json_encode([
            'payload' => $payload,
            'signature' => $securityConfig->sign($payload),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function decode(
        string $packet,
        SecurityConfig $securityConfig = new SecurityConfig(),
    ): array
    {
        $envelope = json_decode($packet, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($envelope['payload'] ?? null)) {
            throw new \InvalidArgumentException('Invalid discovery payload.');
        }
        $payload = $envelope['payload'];
        $securityConfig->verify(
            $payload,
            $payload['timestamp'] ?? null,
            $envelope['signature'] ?? null,
        );
        if (!in_array($payload['action'] ?? null, [self::HEARTBEAT, self::OFFLINE], true)
            || !is_array($payload['node'] ?? null)) {
            throw new \InvalidArgumentException('Invalid discovery message.');
        }
        $node = ServiceNode::fromArray($payload['node']);
        if ($node->getServiceName() === '') {
            throw new \InvalidArgumentException('Discovery node must contain a service name.');
        }
        return [$payload['action'], $node];
    }
}
