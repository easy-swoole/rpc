<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-02-13
 * Time: 22:08
 */

namespace EasySwoole\Rpc;


use EasySwoole\Component\Process\AbstractProcess;
use Swoole\Coroutine\Socket;
use Swoole\Coroutine\Client as CoClient;

class AutoFIndProcess extends AbstractProcess
{
    const UDP_ACTION_HEART_BEAT = 1;
    const UDP_ACTION_OFFLINE = 2;
    /** @var Config */
    protected $config;
    public function run($arg)
    {
        /** @var $arg Config */
        $this->config = $arg;
        $this->addTick(15*1000,function (){
           //每15s对外广播自己的存在
            $client = new CoClient(SWOOLE_UDP);
            $data = new ProtocolPackage();
            $data->setAction(self::UDP_ACTION_HEART_BEAT);
            $data = serialize($data);
            foreach ($this->config->getAutoFindBroadcastAddress() as $address){
                $client->sendto($address,$this->config->getAutoFindListenPort(),$data);
            }
        });
        if(!empty($arg->getAutoFindListenAddress())){
            //创建服务。
            go(function (){
                $socketServer = new Socket(AF_INET,SOCK_DGRAM);
                $socketServer->bind($this->config->getAutoFindListenAddress(),$this->config->getAutoFindListenPort());
                while (1){
                    $peer = null;
                    $request = unserialize($socketServer->recvfrom($peer));
                    if($request instanceof ProtocolPackage){
                        switch ($request->getAction()){
                            case self::UDP_ACTION_HEART_BEAT:{
//                                $node = $request->getArg();
//                                $this->config->getNodeManager()->registerServiceNode($node);
                                break;
                            }
                            case  self::UDP_ACTION_OFFLINE:{
//                                $node = $request->getArg();
//                                $this->config->getNodeManager()->deleteServiceNode($node);
                                break;
                            }
                        }
                    }else{
                        trigger_error('unserialize fail from '.$peer['address'].'@'.$peer['port']);
                    }
                }
            });
        }
    }

    public function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }

    public function onReceive(string $str)
    {
        // TODO: Implement onReceive() method.
    }
}