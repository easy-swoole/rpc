<?php

use EasySwoole\Rpc\Config;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Tests\Service\ModuleOne;
use EasySwoole\Rpc\Tests\Service\ModuleTwo;
use EasySwoole\Rpc\Tests\Service\ServiceOne;
use EasySwoole\Rpc\Tests\Service\ServiceTwo;
use EasySwoole\Rpc\Tests\Service\ServiceException;
use Swoole\Http\Server;

require '../vendor/autoload.php';

$pid = pcntl_fork();
if ($pid > 0) {
    $config = new Config();
    $config->getServer()->setServerIp('127.0.0.1');
    $config->getServer()->setListenPort(9601);


    $rpc = new Rpc($config);

    $service = new ServiceOne();
    $service->addModule(new ModuleOne());
    $service->addModule(new ModuleTwo());

    $serviceException = new ServiceException();

    $rpc->serviceManager()->addService($service);
    $rpc->serviceManager()->addService($serviceException);
    $http = new Server('0.0.0.0', 9501);

    $rpc->attachServer($http);

    $http->on('request', function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($rpc) {
        $client = $rpc->client();
        $path = $request->get['path'] ?? '';
        $ctx = $client->addRequest($path);
        $ctx->setArg($request->get['args'] ?? null);
        $ctx->setOnSuccess(function (Response $response) use (&$ret) {
            $ret = $response->__toString();
        });
        $ctx->setOnFail(function (Response $response) use (&$ret) {
            $ret = $response->__toString();
        });
        $client->exec();
        $response->write($ret);
    });

    $http->start();
} else {
    $config = new Config();
    $config->getServer()->setServerIp('127.0.0.1');
    $config->getServer()->setListenPort(9602);

    $rpc = new Rpc($config);

    $service = new ServiceTwo();
    $service->addModule(new ModuleOne());
    $service->addModule(new ModuleTwo());

    $serviceException = new ServiceException();

    $rpc->serviceManager()->addService($service);
    $rpc->serviceManager()->addService($serviceException);
    $http = new Server('0.0.0.0', 9502);

    $rpc->attachServer($http);

    $http->on('request', function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($rpc) {
        $client = $rpc->client();
        $path = $request->get['path'] ?? '';
        $ctx = $client->addRequest($path);
        $ctx->setArg($request->get['args'] ?? null);
        $ctx->setOnSuccess(function (Response $response) use (&$ret) {
            $ret = $response->__toString();
        });
        $ctx->setOnFail(function (Response $response) use (&$ret) {
            $ret = $response->__toString();
        });
        $client->exec();
        $response->write($ret);
    });

    $http->start();
}