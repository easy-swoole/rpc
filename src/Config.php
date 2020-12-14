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

    function __construct(NodeManagerInterface $manager = null)
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

    function nodeManager(NodeManagerInterface $manager = null):NodeManagerInterface
    {
        if($manager){
            $this->nodeManager = $manager;
        }
        return $this->nodeManager;
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
}