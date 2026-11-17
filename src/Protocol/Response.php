<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;

final class Response implements \JsonSerializable, \Stringable
{
    public function __construct(
        private readonly string $requestUUID,
        private readonly ResponseStatus $status = ResponseStatus::OK,
        private readonly mixed $result = null,
        private readonly mixed $msg = null,
        private readonly NodeHealthStatus $nodeHealthStatus = NodeHealthStatus::HEALTHY,
    ) {}

    public function getStatus(): ResponseStatus { return $this->status; }
    public function getResult(): mixed { return $this->result; }
    public function getMsg(): mixed { return $this->msg; }
    public function getNodeHealthStatus(): NodeHealthStatus { return $this->nodeHealthStatus; }
    public function getRequestUUID(): string { return $this->requestUUID; }
    public function isSuccess(): bool { return $this->status === ResponseStatus::OK; }
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
        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        $status = is_int($data['status'] ?? null) ? ResponseStatus::tryFrom($data['status']) : null;
        if ($status === null) {
            throw new RpcException('RPC response contains an invalid status.');
        }
        $nodeHealthStatus = is_string($data['nodeHealthStatus'] ?? null)
            ? NodeHealthStatus::tryFrom($data['nodeHealthStatus'])
            : null;
        if ($nodeHealthStatus === null) {
            throw new RpcException('RPC response contains an invalid node health status.');
        }
        if (!array_key_exists('requestUUID', $data) || !is_string($data['requestUUID'])) {
            throw new RpcException('RPC response contains an invalid request UUID.');
        }
        return new self(
            $data['requestUUID'],
            status: $status,
            result: $data['result'] ?? null,
            msg: $data['msg'] ?? null,
            nodeHealthStatus: $nodeHealthStatus,
        );
    }

    public function payload(): array { return $this->jsonSerialize(); }

    public function jsonSerialize(): array
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
