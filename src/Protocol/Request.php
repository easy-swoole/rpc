<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\SecurityConfig;

final class Request implements \JsonSerializable
{
    private string $service;
    private string $module;
    private string $action;
    private mixed $arg = null;
    private mixed $clientArg = null;
    private string $requestUUID;
    private int $timestamp;
    private ?string $signature = null;

    public function __construct(
        string $path,
        mixed $arg = null,
        mixed $clientArg = null,
        ?string $requestUUID = null,
        ?int $timestamp = null,
    )
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
        $this->timestamp = $timestamp ?? time();
    }

    public function getService(): string { return $this->service; }
    public function getModule(): string { return $this->module; }
    public function getAction(): string { return $this->action; }
    public function getArg(): mixed { return $this->arg; }
    public function getClientArg(): mixed { return $this->clientArg; }
    public function getRequestUUID(): string { return $this->requestUUID; }
    public function getTimestamp(): int { return $this->timestamp; }
    public function getSignature(): ?string { return $this->signature; }

    public function sign(SecurityConfig $securityConfig, ?int $timestamp = null): self
    {
        $this->timestamp = $timestamp ?? time();
        $this->signature = $securityConfig->sign($this->signaturePayload());
        return $this;
    }

    public function jsonSerialize(): array
    {
        return $this->signaturePayload() + ['signature' => $this->signature];
    }

    public static function fromJson(string $json, SecurityConfig $securityConfig = new SecurityConfig()): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('RPC request must be a JSON object.');
        }
        $payload = [
            'service' => $data['service'] ?? null,
            'module' => $data['module'] ?? null,
            'action' => $data['action'] ?? null,
            'arg' => $data['arg'] ?? null,
            'clientArg' => $data['clientArg'] ?? null,
            'requestUUID' => $data['requestUUID'] ?? null,
            'timestamp' => $data['timestamp'] ?? null,
        ];
        $securityConfig->verify($payload, $data['timestamp'] ?? null, $data['signature'] ?? null);
        $path = implode('.', array_filter([$data['service'] ?? null, $data['module'] ?? null, $data['action'] ?? null], static fn ($v) => $v !== null));
        $request = new self(
            $path,
            $data['arg'] ?? null,
            $data['clientArg'] ?? null,
            $data['requestUUID'] ?? null,
            is_int($data['timestamp'] ?? null) ? $data['timestamp'] : null,
        );
        $request->signature = is_string($data['signature'] ?? null) ? $data['signature'] : null;
        return $request;
    }

    private function signaturePayload(): array
    {
        return [
            'service' => $this->service,
            'module' => $this->module,
            'action' => $this->action,
            'arg' => $this->arg,
            'clientArg' => $this->clientArg,
            'requestUUID' => $this->requestUUID,
            'timestamp' => $this->timestamp,
        ];
    }

    private static function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
