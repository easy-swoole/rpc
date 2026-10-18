<?php

namespace EasySwoole\Rpc\Server;

final class DiscoveryMessage
{
    public const HEARTBEAT = 'heartbeat';
    public const OFFLINE = 'offline';

    public static function encode(string $action, ServiceNode $node, ?string $secret = null): string
    {
        $payload = ['action' => $action, 'node' => $node->jsonSerialize(), 'timestamp' => time()];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return json_encode(['payload' => $payload, 'signature' => $secret === null ? null : hash_hmac('sha256', $body, $secret)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function decode(string $packet, ?string $secret = null): array
    {
        $envelope = json_decode($packet, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($envelope['payload'] ?? null)) {
            throw new \InvalidArgumentException('Invalid discovery payload.');
        }
        $body = json_encode($envelope['payload'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ($secret !== null && !hash_equals(hash_hmac('sha256', $body, $secret), (string) ($envelope['signature'] ?? ''))) {
            throw new \InvalidArgumentException('Invalid discovery signature.');
        }
        $payload = $envelope['payload'];
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
