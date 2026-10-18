<?php

namespace EasySwoole\Rpc\Protocol;

final class Response implements \JsonSerializable, \Stringable
{
    public const STATUS_OK = 0;
    public const STATUS_NOT_AVAILABLE_NODE = 1001;
    public const STATUS_CONNECT_TIMEOUT = 1002;
    public const STATUS_SERVER_TIMEOUT = 1003;
    public const STATUS_PACKAGE_READ_TIMEOUT = 2001;
    public const STATUS_ILLEGAL_PACKAGE = 2002;
    public const STATUS_SERVICE_SHUTDOWN = 3000;
    public const STATUS_SERVICE_NOT_EXIST = 3001;
    public const STATUS_MODULE_NOT_EXIST = 3002;
    public const STATUS_ACTION_NOT_EXIST = 3003;
    public const STATUS_SERVICE_ERROR = 3004;

    private int $status = self::STATUS_OK;
    private mixed $result = null;
    private mixed $msg = null;

    public function __construct(private readonly string $responseUUID) {}
    public function getStatus(): int { return $this->status; }
    public function getResult(): mixed { return $this->result; }
    public function getMsg(): mixed { return $this->msg; }
    public function getResponseUUID(): string { return $this->responseUUID; }
    public function setStatus(int $status): self { $this->status = $status; return $this; }
    public function setResult(mixed $result): self { $this->result = $result; return $this; }
    public function setMsg(mixed $msg): self { $this->msg = $msg; return $this; }
    public function isSuccess(): bool { return $this->status === self::STATUS_OK; }
    public function jsonSerialize(): array { return ['status' => $this->status, 'result' => $this->result, 'msg' => $this->msg, 'responseUUID' => $this->responseUUID]; }
    public function __toString(): string { return json_encode($this, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return (new self((string) ($data['responseUUID'] ?? '')))
            ->setStatus((int) ($data['status'] ?? self::STATUS_ILLEGAL_PACKAGE))
            ->setResult($data['result'] ?? null)->setMsg($data['msg'] ?? null);
    }
}
