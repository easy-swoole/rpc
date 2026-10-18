<?php

namespace EasySwoole\Rpc\Protocol;

enum NodeHealthStatus: string
{
    case HEALTHY = 'healthy';
    case SUSPECT = 'suspect';
    case OFFLINE = 'offline';
}
