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
        // 响应始终回显请求标识，客户端依靠它校验响应是否属于当前请求。
        $response = new Response($request->getRequestUUID());
        $service = $this->registry->find($request->getService());
        if ($service === null) { return $response->setStatus(ResponseStatus::SERVICE_NOT_EXIST); }
        try {
            // Response 由请求处理器创建和返回，Service/Module 只负责修改其内容。
            $service->__dispatch($request, $response);
            return $response;
        } catch (\Throwable $throwable) {
            $this->report($throwable);
            return $this->markServerError($response);
        }
    }
    public function handlePacket(string $payload): string
    {
        try {
            $request = $this->codec->decodeRequest($payload);
        } catch (\Throwable) {
            // 非法数据属于可预期的客户端输入，不应触发服务端全局异常回调。
            return $this->codec->encodeResponse(
                (new Response(''))->setStatus(ResponseStatus::ILLEGAL_PACKAGE)->setMsg('Illegal RPC request.'),
            );
        }

        $response = $this->handle($request);
        try {
            return $this->codec->encodeResponse($response);
        } catch (\Throwable $throwable) {
            // 业务结果无法序列化时仍返回一个最小安全响应，不能让客户端误判为网络故障。
            $this->report($throwable);
            return $this->codec->encodeResponse(
                $this->markServerError(new Response($request->getRequestUUID())),
            );
        }
    }

    private function markServerError(Response $response): Response
    {
        return $response
            ->setStatus(ResponseStatus::SERVICE_ERROR)
            // 异常可能发生在 Action 已写入结果之后，错误响应不能携带半完成的业务数据。
            ->setResult(null)
            ->setNodeHealthStatus(NodeHealthStatus::SUSPECT)
            ->setMsg('Server error.');
    }

    private function report(\Throwable $throwable): void
    {
        if ($this->onException === null) { return; }
        // 日志回调自身失败不能反向破坏 RPC 响应流程。
        try { ($this->onException)($throwable); } catch (\Throwable) {}
    }
}
