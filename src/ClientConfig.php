<?php


namespace EasySwoole\Rpc;


class ClientConfig
{
    /** @var array $clientSettings */
    protected $clientSettings = [];

    /** @var callable $onGlobalSuccess */
    protected $onGlobalSuccess;

    /** @var callable $onGlobalFail */
    protected $onGlobalFail;

    /**
     * @return array
     */
    public function getClientSettings(): array
    {
        return $this->clientSettings;
    }

    /**
     * @param array $clientSettings
     */
    public function setClientSettings(array $clientSettings): void
    {
        $this->clientSettings = $clientSettings;
    }

    /**
     * @return callable|null
     */
    public function getOnGlobalSuccess(): ?callable
    {
        return $this->onGlobalSuccess;
    }

    /**
     * @param callable $onGlobalSuccess
     */
    public function setOnGlobalSuccess(callable $onGlobalSuccess): void
    {
        $this->onGlobalSuccess = $onGlobalSuccess;
    }

    /**
     * @return callable|null
     */
    public function getOnGlobalFail(): ?callable
    {
        return $this->onGlobalFail;
    }

    /**
     * @param callable $onGlobalFail
     */
    public function setOnGlobalFail(callable $onGlobalFail): void
    {
        $this->onGlobalFail = $onGlobalFail;
    }
}