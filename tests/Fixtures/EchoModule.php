<?php

namespace EasySwoole\Rpc\Tests\Fixtures;

use EasySwoole\Rpc\Attribute\RpcAction;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\AbstractInterface\AbstractServiceModule;

final class EchoModule extends AbstractServiceModule
{
    public function moduleName(): string { return 'Echo'; }
    #[RpcAction]
    public function value(mixed $arg, Request $request): mixed { return $arg; }
    #[RpcAction]
    public function fail(): never { throw new \RuntimeException('module failed'); }

    public function internalHelper(): string { return 'must not be exposed'; }
}
