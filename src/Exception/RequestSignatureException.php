<?php

namespace EasySwoole\Rpc\Exception;

final class RequestSignatureException extends RpcException
{
    public function __construct(public readonly string $requestUUID, RpcException $previous)
    {
        parent::__construct('signature verify error', previous: $previous);
    }
}
