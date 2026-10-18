<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\SecurityConfig;

final class Response implements \JsonSerializable, \Stringable
{
    private ResponseStatus $status = ResponseStatus::OK;
    private mixed $result = null;
    private mixed $msg = null;
    private int $timestamp;
    private ?string $signature = null;

    public function __construct(private readonly string $responseUUID, ?int $timestamp = null)
    {
        $this->timestamp = $timestamp ?? time();
    }
    public function getStatus(): ResponseStatus { return $this->status; }
    public function getResult(): mixed { return $this->result; }
    public function getMsg(): mixed { return $this->msg; }
    public function getResponseUUID(): string { return $this->responseUUID; }
    public function getTimestamp(): int { return $this->timestamp; }
    public function getSignature(): ?string { return $this->signature; }
    public function setStatus(ResponseStatus $status): self { $this->status = $status; return $this; }
    public function setResult(mixed $result): self { $this->result = $result; return $this; }
    public function setMsg(mixed $msg): self { $this->msg = $msg; return $this; }
    public function sign(SecurityConfig $securityConfig, ?int $timestamp = null): self
    {
        $this->timestamp = $timestamp ?? time();
        $this->signature = $securityConfig->sign($this->signaturePayload());
        return $this;
    }
    public function verify(SecurityConfig $securityConfig): void
    {
        $securityConfig->verify($this->signaturePayload(), $this->timestamp, $this->signature);
    }
    public function isSuccess(): bool { return $this->status === ResponseStatus::OK; }
    public function jsonSerialize(): array { return $this->signaturePayload() + ['signature' => $this->signature]; }
    public function __toString(): string { return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('RPC response must be a JSON object.');
        }
        $status = isset($data['status']) && is_int($data['status'])
            ? ResponseStatus::tryFrom($data['status'])
            : null;
        $response = (new self(
            (string) ($data['responseUUID'] ?? ''),
            is_int($data['timestamp'] ?? null) ? $data['timestamp'] : null,
        ))
            ->setStatus($status ?? ResponseStatus::ILLEGAL_PACKAGE)
            ->setResult($data['result'] ?? null)->setMsg($data['msg'] ?? null);
        $response->signature = is_string($data['signature'] ?? null) ? $data['signature'] : null;
        return $response;
    }

    private function signaturePayload(): array
    {
        return [
            'status' => $this->status->value,
            'result' => $this->result,
            'msg' => $this->msg,
            'responseUUID' => $this->responseUUID,
            'timestamp' => $this->timestamp,
        ];
    }
}
