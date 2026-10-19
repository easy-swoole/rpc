<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\AbstractInterface\NodeFailurePolicyInterface;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\Protocol\ProtocolCodec;
use EasySwoole\Rpc\Protocol\ProtocolConfig;
use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Rpc\RuntimeErrorReporter;

final class Client
{
    private RequestExecutor $executor;
    public function __construct(
        private readonly AbstractNodeManager $nodeManager,
        private readonly NodeSelectorInterface $nodeSelector,
        private readonly NodeFailurePolicyInterface $failurePolicy,
        SecurityConfig $securityConfig = new SecurityConfig(),
        ProtocolConfig $protocolConfig = new ProtocolConfig(),
        RuntimeErrorReporter $runtimeErrorReporter = new RuntimeErrorReporter(),
    ) {
        // TCP 协议与服务端 attachServer() 固定配套，不对外暴露可替换 Transport。
        $transport = new TcpTransport(
            new ProtocolCodec(new PayloadSigner($securityConfig)),
            $protocolConfig,
        );
        $this->executor = new RequestExecutor($this->nodeManager, $this->nodeSelector, $this->failurePolicy, $transport, $runtimeErrorReporter);
    }
    public function buildRequest(): RequestBatch
    {
        // 批次彼此隔离，同一个 Client 可以在不同协程中并发创建多个批次。
        return new RequestBatch($this->executor);
    }
}
