<?php

namespace EasySwoole\Rpc\Tests\Fixtures;

use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\AbstractInterface\AbstractServiceModule;

final class EchoModule extends AbstractServiceModule
{
    public function moduleName(): string { return 'Echo'; }
    public function value(mixed $arg, Request $request): mixed { return $arg; }
    public function fail(): never { throw new \RuntimeException('module failed'); }
}
