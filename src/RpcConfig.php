<?php

namespace EasySwoole\Rpc;

use EasySwoole\Rpc\Server\DiscoveryConfig;
use EasySwoole\Rpc\Server\ServerConfig;

final class RpcConfig
{
    public function __construct(
        private readonly ServerConfig $server = new ServerConfig(),
        private readonly DiscoveryConfig $discovery = new DiscoveryConfig(),
        private readonly SecurityConfig $security = new SecurityConfig(),
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
}
