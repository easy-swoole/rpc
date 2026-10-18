<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\Server\DiscoveryConfig;
use EasySwoole\Rpc\Server\ServerConfig;
use EasySwoole\Rpc\Protocol\ProtocolConfig;

final class RpcConfig
{
    public function __construct(
        private readonly ServerConfig $server = new ServerConfig(),
        private readonly DiscoveryConfig $discovery = new DiscoveryConfig(),
        private readonly SecurityConfig $security = new SecurityConfig(),
        private readonly ProtocolConfig $protocol = new ProtocolConfig(),
    ) {}

    public function getServer(): ServerConfig
    {
        return $this->server;
    }

    public function getDiscovery(): DiscoveryConfig
    {
        return $this->discovery;
    }

    public function getSecurity(): SecurityConfig
    {
        return $this->security;
    }
    public function getProtocol(): ProtocolConfig { return $this->protocol; }
}
