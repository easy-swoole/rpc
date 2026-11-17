<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Exception\RequestSignatureException;

final class ProtocolCodec
{
    public function __construct(private readonly PayloadSigner $signer) {}

    public function encodeRequest(Request $request): string { return $this->encode($request->jsonSerialize()); }
    public function encodeRequestFrame(string $payload): string { return RequestFrame::pack($payload, $this->signer); }
    public function encodeResponse(Response $response): string { return $this->encode($response->jsonSerialize()); }

    public function decodeRequest(string $json): Request
    {
        $payload = $this->decode($json);
        $invalidField = $this->invalidRequestField($payload);
        if ($invalidField !== null) {
            throw new RpcException(sprintf('RPC request contains an invalid %s.', $invalidField));
        }
        return $this->requestFromPayload($payload);
    }

    public function inspectRequest(string $json): Request
    {
        $envelope = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $payload = $envelope['payload'] ?? null;
        if (!is_array($payload)) {
            throw new RpcException('invalid request envelope');
        }
        $invalidField = $this->invalidRequestField($payload);
        if ($invalidField !== null) {
            throw new RpcException(sprintf('invalid request field: %s', $invalidField));
        }
        try {
            $this->signer->verify($payload, $payload['timestamp'] ?? null, $envelope['signature'] ?? null);
        } catch (RpcException $exception) {
            throw new RequestSignatureException($payload['requestUUID'], $exception);
        }
        return $this->requestFromPayload($payload);
    }

    private function invalidRequestField(array $payload): ?string
    {
        foreach (['service', 'module', 'action'] as $field) {
            $value = $payload[$field] ?? null;
            if (!is_string($value) || $value === '' || str_contains($value, '.')) {
                return $field;
            }
        }
        $uuid = $payload['requestUUID'] ?? null;
        return !is_string($uuid) || $uuid === '' ? 'requestUUID' : null;
    }

    private function requestFromPayload(array $payload): Request
    {
        return new Request(
            implode('.', [$payload['service'], $payload['module'], $payload['action']]),
            $payload['arg'] ?? null,
            $payload['clientArg'] ?? null,
            $payload['requestUUID'],
        );
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
            // 业务对象只序列化一次。签名使用接收端解码时看到的数据，发包复用原始 JSON。
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $signature = $this->signer->sign(json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR));
            return sprintf(
                '{"payload":%s,"signature":%s}',
                $payloadJson,
                json_encode($signature, JSON_THROW_ON_ERROR),
            );
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
