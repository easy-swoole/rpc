<?php

namespace EasySwoole\Rpc\Tests\Fixtures;

use EasySwoole\Rpc\AbstractInterface\AbstractService;

final class WeightedService extends AbstractService
{
    public function serviceName(): string { return 'Weighted'; }
    public function serviceWeight(): int { return 25; }
}
