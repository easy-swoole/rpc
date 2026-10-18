<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\AbstractInterface\TransportInterface;
use EasySwoole\Rpc\Protocol\Protocol;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\ProtocolConfig;
use EasySwoole\Rpc\Protocol\ProtocolCodec;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Server\ServiceNode;

final class TcpTransport implements TransportInterface
{
    public function __construct(private readonly ProtocolCodec $codec, private readonly ProtocolConfig $config = new ProtocolConfig()) {}

    public function send(ServiceNode $node, Request $request, float $timeout): Response
    {
        $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
        $client->set([
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
            'package_max_length' => $this->config->maxPackageSize,
        ]);
        try {
            if (!$client->connect($node->getIp(), $node->getPort(), $timeout)) {
                throw new TransportException(
                    ResponseStatus::CONNECT_TIMEOUT,
                    $client->errMsg ?: 'Unable to connect to RPC node.',
                    $client->errCode,
                );
            }
            if ($client->send(Protocol::pack($this->codec->encodeRequest($request))) === false) {
                throw new TransportException(
                    ResponseStatus::TRANSPORT_ERROR,
                    $client->errMsg ?: 'Unable to send RPC request.',
                    $client->errCode,
                );
            }
            $packet = $client->recv($timeout);
            if (!is_string($packet) || $packet === '') {
                throw new TransportException(
                    ResponseStatus::SERVER_TIMEOUT,
                    $client->errMsg ?: 'RPC response timed out.',
                    $client->errCode,
                );
            }
            try {
                return $this->codec->decodeResponse(Protocol::unpack($packet));
            } catch (\Throwable $throwable) {
                throw new TransportException(
                    ResponseStatus::RESPONSE_DECODE_ERROR,
                    'Unable to decode RPC response: ' . $throwable->getMessage(),
                    previous: $throwable,
                );
            }
        } finally {
            if ($client->isConnected()) {
                $client->close();
            }
        }
    }
}
