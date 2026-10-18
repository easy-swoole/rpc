<?php

namespace EasySwoole\Rpc\Protocol;

final class Request implements \JsonSerializable
{
    private string $service;
    private string $module;
    private string $action;
    private mixed $arg = null;
    private mixed $clientArg = null;
    private string $requestUUID;

    public function __construct(string $path, mixed $arg = null, mixed $clientArg = null, ?string $requestUUID = null)
    {
        $parts = explode('.', $path);
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new \InvalidArgumentException('Request path must be Service.Module.action.');
        }
        $this->service = $parts[0];
        $this->module = $parts[1];
        $this->action = $parts[2];
        $this->arg = $arg;
        $this->clientArg = $clientArg;
        $this->requestUUID = $requestUUID ?? self::uuid();
    }

    public function getService(): string { return $this->service; }
    public function getModule(): string { return $this->module; }
    public function getAction(): string { return $this->action; }
    public function getArg(): mixed { return $this->arg; }
    public function getClientArg(): mixed { return $this->clientArg; }
    public function getRequestUUID(): string { return $this->requestUUID; }

    public function jsonSerialize(): array
    {
        return ['service' => $this->service, 'module' => $this->module, 'action' => $this->action,
            'arg' => $this->arg, 'clientArg' => $this->clientArg, 'requestUUID' => $this->requestUUID];
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $path = implode('.', array_filter([$data['service'] ?? null, $data['module'] ?? null, $data['action'] ?? null], static fn ($v) => $v !== null));
        return new self($path, $data['arg'] ?? null, $data['clientArg'] ?? null, $data['requestUUID'] ?? null);
    }

    private static function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
