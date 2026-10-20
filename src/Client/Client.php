<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\Protocol\ProtocolCodec;
use EasySwoole\Rpc\Protocol\ProtocolConfig;
use EasySwoole\Rpc\SecurityConfig;

final class Client
{
    private RequestExecutor $executor;
    private mixed $onException = null;
    public function __construct(
        AbstractNodeManager $nodeManager,
        NodeSelectorInterface $nodeSelector,
        NodeFailurePolicyInterface $failurePolicy,
        SecurityConfig $securityConfig = new SecurityConfig(),
        ProtocolConfig $protocolConfig = new ProtocolConfig(),
    ) {
        // TCP 协议与服务端 attachServer() 固定配套，不对外暴露可替换 Transport。
        $transport = new TcpTransport(
            new ProtocolCodec(new PayloadSigner($securityConfig)),
            $protocolConfig,
        );
        $this->executor = new RequestExecutor($nodeManager, $nodeSelector, $failurePolicy, $transport);
    }
    public function buildRequest(): RequestBatch
    {
        // 批次彼此隔离，同一个 Client 可以在不同协程中并发创建多个批次。
        return new RequestBatch(
            $this->executor,
            function (\Throwable $throwable): void { $this->reportException($throwable); },
        );
    }

    public function setOnException(callable $callback): self
    {
        $this->onException = $callback;
        return $this;
    }

    private function reportException(\Throwable $throwable): void
    {
        if (!is_callable($this->onException)) {
            // 未配置客户端异常处理器时，不允许内部转发闭包静默吞掉异常。
            throw $throwable;
        }
        // onException 属于异常接管入口；回调自身异常同样必须直接暴露。
        ($this->onException)($throwable);
    }
}
