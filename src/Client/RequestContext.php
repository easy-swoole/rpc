<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\Exception\RpcException;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Server\ServiceNode;

final class RequestContext
{
    private mixed $arg = null;
    private mixed $clientArg = null;
    private ?ServiceNode $serviceNode = null;
    private int $retryTimes = 0;
    private $onSuccess = null;
    private $onFail = null;

    public function __construct(private readonly string $requestPath) {}
    public function getRequestPath(): string { return $this->requestPath; }
    public function getArg(): mixed { return $this->arg; }
    public function getClientArg(): mixed { return $this->clientArg; }
    public function getServiceNode(): ?ServiceNode { return $this->serviceNode; }
    public function getRetryTimes(): int { return $this->retryTimes; }
    public function setArg(mixed $arg): self { $this->arg = $arg; return $this; }
    public function setClientArg(mixed $arg): self { $this->clientArg = $arg; return $this; }
    public function setServiceNode(?ServiceNode $node): self
    {
        if ($node !== null) {
            $serviceName = explode('.', $this->requestPath, 2)[0];
            if ($node->getServiceName() !== $serviceName) {
                throw new RpcException(sprintf(
                    'Service node "%s" belongs to service "%s", but request path requires service "%s".',
                    $node->getNodeId(),
                    $node->getServiceName(),
                    $serviceName,
                ));
            }
        }
        $this->serviceNode = $node;
        return $this;
    }
    public function enableRetry(int $times): self { $this->retryTimes = max(0, $times); return $this; }
    public function setOnSuccess(?callable $callback): self { $this->onSuccess = $callback; return $this; }
    public function setOnFail(?callable $callback): self { $this->onFail = $callback; return $this; }
    public function notify(Response $response): void
    {
        $callback = $response->isSuccess() ? $this->onSuccess : $this->onFail;
        if ($callback !== null) { $callback($response, $this); }
    }
}
