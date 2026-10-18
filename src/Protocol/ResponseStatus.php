<?php

namespace EasySwoole\Rpc\Protocol;

enum ResponseStatus: int
{
    case OK = 0;
    case NOT_AVAILABLE_NODE = 1001;
    case CONNECT_TIMEOUT = 1002;
    case SERVER_TIMEOUT = 1003;
    case TRANSPORT_ERROR = 1004;
    case RESPONSE_DECODE_ERROR = 1005;
    case PACKAGE_READ_TIMEOUT = 2001;
    case ILLEGAL_PACKAGE = 2002;
    case SERVICE_SHUTDOWN = 3000;
    case SERVICE_NOT_EXIST = 3001;
    case MODULE_NOT_EXIST = 3002;
    case ACTION_NOT_EXIST = 3003;
    case SERVICE_ERROR = 3004;
}
