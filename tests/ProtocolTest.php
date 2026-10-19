<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Rpc\Protocol\Protocol;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Protocol\ProtocolCodec;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\Exception\RpcException;
use PHPUnit\Framework\TestCase;

final class ProtocolTest extends TestCase
{
    public function testPacketRoundTrip(): void
    {
        $payload = '{"hello":"世界"}';
        $packet = Protocol::pack($payload);
        self::assertSame(strlen($payload), Protocol::payloadLength(substr($packet, 0, 4)));
        self::assertSame($payload, Protocol::unpack($packet));
    }

    public function testRejectsInvalidPacketLength(): void
    {
        $this->expectException(RpcException::class);
        Protocol::unpack(pack('N', 100) . 'short');
    }

    public function testRequestJsonRoundTripKeepsLegacyThreePartPath(): void
    {
        $request = new Request('Test.Echo.value', ['id' => 1], ['trace' => 'x'], 'request-id');
        $codec = new ProtocolCodec(new PayloadSigner(new SecurityConfig()));
        $copy = $codec->decodeRequest($codec->encodeRequest($request));
        self::assertSame('Test', $copy->getService());
        self::assertSame('Echo', $copy->getModule());
        self::assertSame('value', $copy->getAction());
        self::assertSame(['id' => 1], $copy->getArg());
        self::assertSame('request-id', $copy->getRequestUUID());
    }

    public function testRequestCanBeRenderedAsBusinessJson(): void
    {
        $request = new Request('User.Profile.detail', ['id' => 1001], ['traceId' => '链路-1'], 'request-id');

        self::assertSame(
            '{"service":"User","module":"Profile","action":"detail","arg":{"id":1001},"clientArg":{"traceId":"链路-1"},"requestUUID":"request-id"}',
            $request->toJson(),
        );
        self::assertArrayNotHasKey('timestamp', json_decode($request->toJson(), true, 512, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('signature', json_decode($request->toJson(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRequestRequiresServiceModuleActionPath(): void
    {
        $this->expectException(RpcException::class);
        new Request('Test.action');
    }

    public function testResponseJsonRoundTrip(): void
    {
        $response = (new Response('request-id'))->setStatus(ResponseStatus::SERVICE_ERROR)->setResult(['ok' => false])->setMsg('bad');
        $copy = Response::fromJson($response->toJson());
        self::assertSame($response->jsonSerialize(), $copy->jsonSerialize());
        self::assertSame(ResponseStatus::SERVICE_ERROR, $copy->getStatus());
        self::assertSame(ResponseStatus::SERVICE_ERROR->value, $copy->jsonSerialize()['status']);
        self::assertSame($response->toJson(), (string) $response);
    }

    public function testRejectsUnknownResponseStatus(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('invalid status');
        Response::fromJson('{"status":9999,"nodeHealthStatus":"healthy","requestUUID":"id"}');
    }

    public function testRejectsInvalidResponseRequestUuidType(): void
    {
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('invalid request UUID');
        Response::fromJson('{"status":0,"nodeHealthStatus":"healthy","requestUUID":[]}');
    }

    public function testSignedRequestRoundTripAndTamperDetection(): void
    {
        $security = new SecurityConfig(apiKey: 'shared-api-key');
        $codec = new ProtocolCodec(new PayloadSigner($security));
        $request = new Request('Test.Echo.value', ['id' => 1], requestUUID: 'request-id');
        $json = $codec->encodeRequest($request);

        self::assertSame('request-id', $codec->decodeRequest($json)->getRequestUUID());

        $tampered = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $tampered['payload']['arg']['id'] = 2;
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('signature');
        $codec->decodeRequest(json_encode($tampered, JSON_THROW_ON_ERROR));
    }

    public function testSignatureIsAddedOnlyWhenTheRequestIsSerializedForTransport(): void
    {
        $request = new Request('Test.Echo.value', requestUUID: 'request-id');
        self::assertArrayNotHasKey('timestamp', $request->jsonSerialize());
        self::assertArrayNotHasKey('signature', $request->jsonSerialize());

        $encoded = (new ProtocolCodec(new PayloadSigner(new SecurityConfig(apiKey: 'key'))))
            ->encodeRequest($request);
        $envelope = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsInt($envelope['payload']['timestamp']);
        self::assertIsString($envelope['signature']);
        self::assertArrayNotHasKey('signature', $envelope['payload']);
    }

    public function testRejectsExpiredSignedRequest(): void
    {
        $security = new SecurityConfig(apiKey: 'shared-api-key', timestampToleranceSeconds: 10);
        $signer = new PayloadSigner($security);
        $payload = (new Request('Test.Echo.value'))->jsonSerialize() + ['timestamp' => time() - 11];
        $json = json_encode(['payload' => $payload, 'signature' => $signer->sign($payload)], JSON_THROW_ON_ERROR);

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('timestamp');
        (new ProtocolCodec($signer))->decodeRequest($json);
    }

    public function testRequestSignatureUsesCanonicalObjectKeyOrder(): void
    {
        $security = new SecurityConfig(apiKey: 'shared-api-key');
        $codec = new ProtocolCodec(new PayloadSigner($security));
        $request = new Request('Test.Echo.value', ['b' => 2, 'a' => 1]);
        $data = json_decode($codec->encodeRequest($request), true, 512, JSON_THROW_ON_ERROR);
        $data['payload']['arg'] = ['a' => 1, 'b' => 2];

        self::assertSame(
            $request->getRequestUUID(),
            $codec->decodeRequest(json_encode($data, JSON_THROW_ON_ERROR))->getRequestUUID(),
        );
    }

    public function testSignedResponseRoundTripAndTamperDetection(): void
    {
        $security = new SecurityConfig(apiKey: 'shared-api-key');
        $codec = new ProtocolCodec(new PayloadSigner($security));
        $response = (new Response('request-id'))->setResult(['b' => 2, 'a' => 1]);
        $json = $codec->encodeResponse($response);
        $copy = $codec->decodeResponse($json);
        self::assertSame('request-id', $copy->getRequestUUID());

        $tampered = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $tampered['payload']['nodeHealthStatus'] = NodeHealthStatus::OFFLINE->value;
        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('signature');
        $codec->decodeResponse(json_encode($tampered, JSON_THROW_ON_ERROR));
    }
}
