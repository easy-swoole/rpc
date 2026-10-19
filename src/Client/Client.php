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
        $transport = new TcpTransport(
            new ProtocolCodec(new PayloadSigner($securityConfig)),
            $protocolConfig,
        );
        $this->executor = new RequestExecutor($this->nodeManager, $this->nodeSelector, $this->failurePolicy, $transport, $runtimeErrorReporter);
    }
    public function buildRequest(): RequestBatch
    {
        return new RequestBatch($this->executor);
    }
}
