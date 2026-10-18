<?php

namespace EasySwoole\Rpc\Protocol;

use EasySwoole\Rpc\Exception\RpcException;

final class Request implements \JsonSerializable
{
    private string $service;
    private string $module;
    private string $action;
    private mixed $arg = null;
    private mixed $clientArg = null;
    private string $requestUUID;

    public function __construct(
        string $path,
        mixed $arg = null,
        mixed $clientArg = null,
        ?string $requestUUID = null,
    )
    {
        $parts = self::validatePath($path);
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

    /** @return array{string, string, string} */
    public static function validatePath(string $path): array
    {
        $parts = explode('.', $path);
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new RpcException('Request path must be Service.Module.action.');
        }
        return [$parts[0], $parts[1], $parts[2]];
    }

    public function jsonSerialize(): array
    {
        return [
            'service' => $this->service,
            'module' => $this->module,
            'action' => $this->action,
            'arg' => $this->arg,
            'clientArg' => $this->clientArg,
            'requestUUID' => $this->requestUUID,
        ];
    }

    private static function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
