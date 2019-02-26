<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-02-24
 * Time: 12:26
 */

namespace EasySwoole\Rpc\AutoFind;


use EasySwoole\Component\Openssl;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Request;
use EasySwoole\Rpc\ServiceNode;
use Swoole\Coroutine\Socket;
use Swoole\Coroutine\Client as CoClient;

class Process extends AbstractProcess
{

    const UDP_ACTION_HEART_BEAT = 1;
    const UDP_ACTION_OFFLINE = 2;
    /** @var Config */
    protected $config;
    public function run($arg)
    {
        /** @var $arg Config */
        $this->config = $arg;
        $this->addTick(5*1000,function (){
            //每5s对外广播自己的存在
            $this->nodeBroadcast(self::UDP_ACTION_HEART_BEAT);
        });
        $this->delay(500,function (){
            $this->nodeBroadcast(self::UDP_ACTION_HEART_BEAT);
        });
        if(!empty($arg->getAutoFindConfig()->getAutoFindListenAddress())){
            $address = explode(':',$arg->getAutoFindConfig()->getAutoFindListenAddress());
            $socketServer = new Socket(AF_INET,SOCK_DGRAM);
            $socketServer->bind($address[0],$address[1]);
            while (1){
                $peer = null;
                $data = $socketServer->recvfrom($peer);
                if($this->config->getAutoFindConfig()->getEncryptKey()){
                    $openssl = new Openssl($this->config->getAutoFindConfig()->getEncryptKey());
                    $data = $openssl->decrypt($data);
                }
                $request = unserialize($data);
                if($request instanceof Request){
                    /** @var ServiceNode $node */
                    $node = $request->getArg();
                    if(empty($node->getServiceIp())){
                        $node->setServiceIp($peer['address']);
                    }
                    switch ($request->getAction()){
                        case self::UDP_ACTION_HEART_BEAT:{
                                $this->config->getNodeManager()->registerServiceNode($node);
                                break;
                        }
                        case  self::UDP_ACTION_OFFLINE:{
                                $this->config->getNodeManager()->deleteServiceNode($node);
                                break;
                        }
                    }
                }else{
                    trigger_error('unserialize fail from '.$peer['address'].'@'.$peer['port']);
                }
            }
        }
    }

    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
        $this->nodeBroadcast(self::UDP_ACTION_OFFLINE);
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }

    private function nodeBroadcast(int $command)
    {
        $client = new CoClient(SWOOLE_UDP);
        $data = new Request();
        $data->setAction($command);
        $node = new ServiceNode($this->config->toArray());
        $data->setArg($node);
        $data = serialize($data);
        if($this->config->getAutoFindConfig()->getEncryptKey()){
            $openssl = new Openssl($this->config->getAutoFindConfig()->getEncryptKey());
            $data = $openssl->encrypt($data);
        }
        foreach ($this->config->getAutoFindConfig()->getAutoFindBroadcastAddress() as $address){
            $address = explode(':',$address);
            $client->sendto($address[0],$address[1],$data);
        }
    }
}