<?php


namespace EasySwoole\Rpc\Client;


use EasySwoole\Rpc\Server\ServiceNode;

class RequestContext
{
    /** @var string */
    private $requestPath;
    /** @var int|null */
    private $serviceVersion;
    /** @var mixed */
    private $arg;
    /** @var callable|null */
    private $onSuccess;
    /** @var callable|null */
    private $onFail;
    /** @var ServiceNode|null */
    private $serviceNode;

    /**
     * @return string
     */
    public function getRequestPath(): string
    {
        return $this->requestPath;
    }

    /**
     * @param string $requestPath
     */
    public function setRequestPath(string $requestPath): void
    {
        $this->requestPath = $requestPath;
    }

    /**
     * @return int|null
     */
    public function getServiceVersion(): ?int
    {
        return $this->serviceVersion;
    }

    /**
     * @param int|null $serviceVersion
     */
    public function setServiceVersion(?int $serviceVersion): void
    {
        $this->serviceVersion = $serviceVersion;
    }

    /**
     * @return mixed
     */
    public function getArg()
    {
        return $this->arg;
    }

    /**
     * @param mixed $arg
     */
    public function setArg($arg): void
    {
        $this->arg = $arg;
    }

    /**
     * @return callable|null
     */
    public function getOnSuccess(): ?callable
    {
        return $this->onSuccess;
    }

    /**
     * @param callable|null $onSuccess
     */
    public function setOnSuccess(?callable $onSuccess): void
    {
        $this->onSuccess = $onSuccess;
    }

    /**
     * @return callable|null
     */
    public function getOnFail(): ?callable
    {
        return $this->onFail;
    }

    /**
     * @param callable|null $onFail
     */
    public function setOnFail(?callable $onFail): void
    {
        $this->onFail = $onFail;
    }

    /**
     * @return ServiceNode|null
     */
    public function getServiceNode(): ?ServiceNode
    {
        return $this->serviceNode;
    }

    /**
     * @param ServiceNode|null $serviceNode
     */
    public function setServiceNode(?ServiceNode $serviceNode): void
    {
        $this->serviceNode = $serviceNode;
    }
}