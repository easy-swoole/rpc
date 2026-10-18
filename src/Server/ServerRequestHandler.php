<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Protocol\ProtocolCodec;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseStatus;

final class ServerRequestHandler
{
    private $onException = null;
    public function __construct(private readonly ServiceRegistry $registry, private readonly ProtocolCodec $codec) {}
    public function setOnException(?callable $callback): void { $this->onException = $callback; }
    public function handle(Request $request): Response
    {
        $response = new Response($request->getRequestUUID());
        $service = $this->registry->find($request->getService());
        if ($service === null) { return $response->setStatus(ResponseStatus::SERVICE_NOT_EXIST); }
        try { return $service->__dispatch($request, $response); }
        catch (\Throwable $throwable) {
            $response->setStatus(ResponseStatus::SERVICE_ERROR)->setNodeHealthStatus(NodeHealthStatus::SUSPECT)->setMsg('Server error.');
            $this->report($throwable);
            return $response;
        }
    }
    public function handlePacket(string $payload): string
    {
        try { $response = $this->handle($this->codec->decodeRequest($payload)); }
        catch (\Throwable) { $response = (new Response(''))->setStatus(ResponseStatus::ILLEGAL_PACKAGE)->setMsg('Illegal RPC request.'); }
        return $this->codec->encodeResponse($response);
    }
    private function report(\Throwable $throwable): void
    {
        if ($this->onException === null) { return; }
        try { ($this->onException)($throwable); } catch (\Throwable) {}
    }
}
