<?php

namespace EasySwoole\Rpc\Tests\Fixtures;

use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\AbstractInterface\AbstractService;

final class TestService extends AbstractService
{
    public function serviceName(): string { return 'Test'; }
    public function echo(mixed $arg, Request $request): mixed { return $arg; }
    public function fail(): never { throw new \RuntimeException('service failed'); }
}
