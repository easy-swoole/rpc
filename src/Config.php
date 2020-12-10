<?php


namespace EasySwoole\Rpc;
use EasySwoole\Rpc\Config\Client as ClientConfig;
use EasySwoole\Rpc\Config\Server;
use EasySwoole\Rpc\Config\UdpAssist;

class Config
{
    /** @var ClientConfig */
    private $client;
    /** @var Server */
    private $server;
    /** @var UdpAssist */
    private $udpAssist;

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
     * @return UdpAssist
     */
    public function getUdpAssist(): UdpAssist
    {
        if(!$this->udpAssist){
            $this->udpAssist = new UdpAssist();
        }
        return $this->udpAssist;
    }

    /**
     * @param UdpAssist $udpAssist
     */
    public function setUdpAssist(UdpAssist $udpAssist): void
    {
        $this->udpAssist = $udpAssist;
    }
}