<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;

final class ProtocolCodec
{
    public function __construct(private readonly PayloadSigner $signer) {}

    public function encodeRequest(Request $request): string { return $this->encode($request->jsonSerialize()); }
    public function encodeResponse(Response $response): string { return $this->encode($response->payload()); }

    public function decodeRequest(string $json): Request
    {
        $payload = $this->decode($json);
        foreach (['service', 'module', 'action', 'requestUUID'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field]) || $payload[$field] === '') {
                throw new RpcException(sprintf('RPC request contains an invalid %s.', $field));
            }
        }
        $path = implode('.', [$payload['service'], $payload['module'], $payload['action']]);
        return new Request($path, $payload['arg'] ?? null, $payload['clientArg'] ?? null, $payload['requestUUID']);
    }

    public function decodeResponse(string $json): Response
    {
        return Response::fromArray($this->decode($json));
    }

    private function encode(array $payload): string
    {
        // 时间戳参与签名但不进入 Request/Response 对象，避免对象创建时间影响实际发送时间。
        $payload['timestamp'] = time();
        try {
            return json_encode(['payload' => $payload, 'signature' => $this->signer->sign($payload)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RpcException('Unable to encode RPC envelope.', previous: $exception);
        }
    }

    private function decode(string $json): array
    {
        try {
            $envelope = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RpcException('Unable to decode RPC envelope.', previous: $exception);
        }
        if (!is_array($envelope) || !is_array($envelope['payload'] ?? null)) { throw new RpcException('Invalid RPC envelope.'); }
        $payload = $envelope['payload'];
        // 必须先验证包含 timestamp 的完整载荷，验证通过后再交给业务对象。
        $this->signer->verify($payload, $payload['timestamp'] ?? null, $envelope['signature'] ?? null);
        unset($payload['timestamp']);
        return $payload;
    }
}
