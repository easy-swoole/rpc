<?php

namespace EasySwoole\Rpc\Server;

enum ServerAttachStatus
{
    case PENDING;
    case ATTACHING;
    case ATTACHED;
    case FAILED;
}
