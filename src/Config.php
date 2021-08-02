<?php


namespace EasySwoole\Rpc;
use EasySwoole\Rpc\Config\Client as ClientConfig;
use EasySwoole\Rpc\Config\Server;
use EasySwoole\Rpc\Config\Assist;
use EasySwoole\Rpc\NodeManager\MemoryManager;
use EasySwoole\Rpc\NodeManager\NodeManagerInterface;
use EasySwoole\Utility\Random;

class Config
{
    private $serverName = "EasySwoole";
    /** @var ClientConfig */
    private $client;
    /** @var Server */
    private $server;
    /** @var Assist */
    private $assist;
    /** @var NodeManagerInterface */
    private $nodeManager;
    /** @var string */
    private $nodeId;
    /** @var callable|null */
    private $onException;
    private $maxMem = '512M';

    public function __construct(NodeManagerInterface $manager = null)
    {
        $this->nodeId = Random::character(10);
        if($manager == null){
            $manager = new MemoryManager();
        }
        $this->nodeManager = $manager;
    }

    /**
     * @return ClientConfig
     */
    public function getClient(): ClientConfig
    {
        if(!$this->client){
            $this->client = new ClientConfig();
        }
        return $this->client;
    }

    /**
     * @param ClientConfig $client
     */
    public function setClient(ClientConfig $client): void
    {
        $this->client = $client;
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        if(!$this->server){
            $this->server = new Server();
        }
        return $this->server;
    }

    /**
     * @param Server $server
     */
    public function setServer(Server $server): void
    {
        $this->server = $server;
    }

    /**
     * @return Assist
     */
    public function getAssist(): Assist
    {
        if(!$this->assist){
            $this->assist = new Assist();
        }
        return $this->assist;
    }

    /**
     * @param Assist $assist
     */
    public function setAssist(Assist $assist): void
    {
        $this->assist = $assist;
    }

    /**
     * @return string
     */
    public function getServerName(): string
    {
        return $this->serverName;
    }

    /**
     * @param string $serverName
     */
    public function setServerName(string $serverName): void
    {
        $this->serverName = $serverName;
    }

    public function getNodeManager():?NodeManagerInterface
    {
        return $this->nodeManager;
    }

    public function setNodeManager(NodeManagerInterface $manager)
    {
        $this->nodeManager = $manager;
    }

    /**
     * @return string
     */
    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    /**
     * @param string $nodeId
     */
    public function setNodeId(string $nodeId): void
    {
        $this->nodeId = $nodeId;
    }

    /**
     * @return callable|null
     */
    public function getOnException(): ?callable
    {
        return $this->onException;
    }

    /**
     * @param callable|null $onException
     */
    public function setOnException(?callable $onException): void
    {
        $this->onException = $onException;
    }

    /**
     * @return string
     */
    public function getMaxMem(): string
    {
        return $this->maxMem;
    }

    /**
     * @param string $maxMem
     */
    public function setMaxMem(string $maxMem): void
    {
        $this->maxMem = $maxMem;
    }
}