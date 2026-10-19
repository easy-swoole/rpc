<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;

final class Response implements \JsonSerializable, \Stringable
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
    public function setStatus(ResponseStatus $status): self { $this->status = $status; return $this; }
    public function setResult(mixed $result): self { $this->result = $result; return $this; }
    public function setMsg(mixed $msg): self { $this->msg = $msg; return $this; }
    public function setNodeHealthStatus(NodeHealthStatus $nodeHealthStatus): self
    {
        $this->nodeHealthStatus = $nodeHealthStatus;
        return $this;
    }
    public function isSuccess(): bool { return $this->status === ResponseStatus::OK; }
    public function jsonSerialize(): array { return $this->payload(); }
    public function toJson(): string
    {
        try {
            return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RpcException('Unable to encode RPC response.', previous: $exception);
        }
    }
    public function __toString(): string { return $this->toJson(); }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RpcException('Unable to decode RPC response.', previous: $exception);
        }
        if (!is_array($data)) {
            throw new RpcException('RPC response must be a JSON object.');
        }
        $status = isset($data['status']) && is_int($data['status'])
            ? ResponseStatus::tryFrom($data['status'])
            : null;
        $nodeHealthStatus = !array_key_exists('nodeHealthStatus', $data)
            ? NodeHealthStatus::HEALTHY
            : (is_string($data['nodeHealthStatus']) ? NodeHealthStatus::tryFrom($data['nodeHealthStatus']) : null);
        if ($nodeHealthStatus === null) {
            throw new RpcException('RPC response contains an invalid node health status.');
        }
        return (new self((string) ($data['requestUUID'] ?? '')))
            ->setStatus($status ?? ResponseStatus::ILLEGAL_PACKAGE)
            ->setNodeHealthStatus($nodeHealthStatus)
            ->setResult($data['result'] ?? null)->setMsg($data['msg'] ?? null);
    }

    public function payload(): array
    {
        return [
            'status' => $this->status->value,
            'result' => $this->result,
            'msg' => $this->msg,
            'nodeHealthStatus' => $this->nodeHealthStatus->value,
            'requestUUID' => $this->requestUUID,
        ];
    }
}
