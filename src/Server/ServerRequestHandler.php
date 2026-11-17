<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Protocol\ProtocolCodec;
use EasySwoole\Rpc\Protocol\ProtocolConfig;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\ResponseBuilder;
use EasySwoole\Rpc\Protocol\ResponseStatus;

final class ServerRequestHandler
{
    private $onException = null;
    public function __construct(
        private readonly ServiceRegistry $registry,
        private readonly ProtocolCodec $codec,
        private readonly ProtocolConfig $protocolConfig = new ProtocolConfig(),
    ) {}
    public function setOnException(?callable $callback): void { $this->onException = $callback; }
    public function handle(Request $request): Response
    {
        // 响应始终回显请求标识，客户端依靠它校验响应是否属于当前请求。
        $response = new ResponseBuilder($request->getRequestUUID());
        $service = $this->registry->find($request->getService());
        if ($service === null) { return $response->setStatus(ResponseStatus::SERVICE_NOT_EXIST)->build(); }
        try {
            // Service/Module 在构建阶段修改响应，处理器在分发结束后生成只读响应。
            $service->__dispatch($request, $response);
            return $response->build();
        } catch (\Throwable $throwable) {
            $this->report($throwable);
            return $this->serverError($request->getRequestUUID());
        }
    }
    public function handlePacket(string $payload): string
    {
        try {
            $request = $this->codec->decodeRequest($payload);
        } catch (RpcException) {
            // 非法数据属于可预期的客户端输入，不应触发服务端全局异常回调。
            return $this->encodeWithinLimit(
                new Response('', status: ResponseStatus::ILLEGAL_PACKAGE, msg: 'Illegal RPC request.'),
            );
        }

        $response = $this->handle($request);
        try {
            $encoded = $this->codec->encodeResponse($response);
        } catch (\Throwable $throwable) {
            // 业务结果无法序列化时仍返回一个最小安全响应，不能让客户端误判为网络故障。
            $this->report($throwable);
            return $this->encodeWithinLimit(
                $this->serverError($request->getRequestUUID(), NodeHealthStatus::HEALTHY),
            );
        }
        if ($this->protocolConfig->payloadFits($encoded)) {
            return $encoded;
        }

        if ($this->onException !== null) {
            $this->report(new RpcException('RPC response package exceeds the maximum size.'));
        }
        return $this->encodeWithinLimit(
            new Response($request->getRequestUUID(), status: ResponseStatus::SERVICE_ERROR),
        );
    }

    private function encodeWithinLimit(Response $response): string
    {
        $encoded = $this->codec->encodeResponse($response);
        if (!$this->protocolConfig->payloadFits($encoded)) {
            throw new RpcException('RPC error response package exceeds the maximum size.');
        }
        return $encoded;
    }

    private function serverError(string $requestUUID, NodeHealthStatus $nodeHealthStatus = NodeHealthStatus::SUSPECT): Response
    {
        return new Response(
            $requestUUID,
            status: ResponseStatus::SERVICE_ERROR,
            msg: 'Server error.',
            nodeHealthStatus: $nodeHealthStatus,
        );
    }

    private function report(\Throwable $throwable): void
    {
        if ($this->onException === null) {
            // 未配置全局异常入口时不能静默吞错，直接交还调用层处理。
            throw $throwable;
        }
        // 日志回调自身失败不能反向破坏 RPC 响应流程。
        try { ($this->onException)($throwable); } catch (\Throwable) {}
    }
}
