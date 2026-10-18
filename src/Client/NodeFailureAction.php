<?php

namespace EasySwoole\Rpc\Client;

enum NodeFailureAction
{
    case KEEP;
    case RETRY_ANOTHER;
    case OFFLINE;
}
