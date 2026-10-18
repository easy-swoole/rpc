<?php

namespace EasySwoole\Rpc\Tests\Fixtures;

use EasySwoole\Rpc\Attribute\RpcAction;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\AbstractInterface\AbstractService;

final class TestService extends AbstractService
{
    public function serviceName(): string { return 'Test'; }
    #[RpcAction]
    public function echo(mixed $arg, Request $request): mixed { return $arg; }
    #[RpcAction]
    public function fail(): never { throw new \RuntimeException('service failed'); }

    public function internalHelper(): string { return 'must not be exposed'; }
}
