<?php

namespace EasySwoole\Rpc\Protocol;

final class ResponseBuilder
{
    private ResponseStatus $status = ResponseStatus::OK;
    private mixed $result = null;
    private mixed $msg = null;
    private NodeHealthStatus $nodeHealthStatus = NodeHealthStatus::HEALTHY;

    public function __construct(private readonly string $requestUUID) {}

    public function getStatus(): ResponseStatus { return $this->status; }
    public function getResult(): mixed { return $this->result; }
    public function getMsg(): mixed { return $this->msg; }
    public function getNodeHealthStatus(): NodeHealthStatus { return $this->nodeHealthStatus; }
    public function getRequestUUID(): string { return $this->requestUUID; }
    public function isSuccess(): bool { return $this->status === ResponseStatus::OK; }

    public function setStatus(ResponseStatus $status): self { $this->status = $status; return $this; }
    public function setResult(mixed $result): self { $this->result = $result; return $this; }
    public function setMsg(mixed $msg): self { $this->msg = $msg; return $this; }
    public function setNodeHealthStatus(NodeHealthStatus $nodeHealthStatus): self
    {
        $this->nodeHealthStatus = $nodeHealthStatus;
        return $this;
    }

    public function build(): Response
    {
        return new Response(
            $this->requestUUID,
            status: $this->status,
            result: $this->result,
            msg: $this->msg,
            nodeHealthStatus: $this->nodeHealthStatus,
        );
    }
}
