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
        $path = implode('.', array_filter([$payload['service'] ?? null, $payload['module'] ?? null, $payload['action'] ?? null], static fn ($v) => $v !== null));
        return new Request($path, $payload['arg'] ?? null, $payload['clientArg'] ?? null, $payload['requestUUID'] ?? null);
    }

    public function decodeResponse(string $json): Response
    {
        $payload = $this->decode($json);
        return Response::fromJson(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function encode(array $payload): string
    {
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
        $this->signer->verify($payload, $payload['timestamp'] ?? null, $envelope['signature'] ?? null);
        unset($payload['timestamp']);
        return $payload;
    }
}
