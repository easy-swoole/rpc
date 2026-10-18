<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\Protocol\ResponseStatus;

final class TransportException extends \RuntimeException
{
    public function __construct(
        private readonly ResponseStatus $responseStatus,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponseStatus(): ResponseStatus
    {
        return $this->responseStatus;
    }
}
