<?php

namespace EasySwoole\Rpc\Tests\Fixtures;

use EasySwoole\Rpc\AbstractInterface\AbstractService;

final class TestService extends AbstractService
{
    public function serviceName(): string { return 'Test'; }

    public function internalHelper(): string { return 'must not be exposed'; }
}
