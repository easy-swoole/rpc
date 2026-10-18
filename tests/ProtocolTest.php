<?php

namespace EasySwoole\Rpc\Tests;

use EasySwoole\Rpc\Protocol\Protocol;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;
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
        $this->expectException(\InvalidArgumentException::class);
        Protocol::unpack(pack('N', 100) . 'short');
    }

    public function testRequestJsonRoundTripKeepsLegacyThreePartPath(): void
    {
        $request = new Request('Test.Echo.value', ['id' => 1], ['trace' => 'x'], 'request-id');
        $copy = Request::fromJson(json_encode($request, JSON_THROW_ON_ERROR));
        self::assertSame('Test', $copy->getService());
        self::assertSame('Echo', $copy->getModule());
        self::assertSame('value', $copy->getAction());
        self::assertSame(['id' => 1], $copy->getArg());
        self::assertSame('request-id', $copy->getRequestUUID());
    }

    public function testRequestRequiresServiceModuleActionPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request('Test.action');
    }

    public function testResponseJsonRoundTrip(): void
    {
        $response = (new Response('response-id'))->setStatus(ResponseStatus::SERVICE_ERROR)->setResult(['ok' => false])->setMsg('bad');
        $copy = Response::fromJson((string) $response);
        self::assertSame($response->jsonSerialize(), $copy->jsonSerialize());
        self::assertSame(ResponseStatus::SERVICE_ERROR, $copy->getStatus());
        self::assertSame(ResponseStatus::SERVICE_ERROR->value, $copy->jsonSerialize()['status']);
    }

    public function testUnknownResponseStatusIsTreatedAsIllegalPackage(): void
    {
        $response = Response::fromJson('{"status":9999,"responseUUID":"id"}');

        self::assertSame(ResponseStatus::ILLEGAL_PACKAGE, $response->getStatus());
    }
}
