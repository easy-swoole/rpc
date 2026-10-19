<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\Protocol\Protocol;
use EasySwoole\Rpc\Protocol\ResponseStatus;
use EasySwoole\Rpc\Protocol\ProtocolConfig;
use EasySwoole\Rpc\Protocol\ProtocolCodec;
use EasySwoole\Rpc\Protocol\Request;
use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\SecurityConfig;
use EasySwoole\Rpc\Server\ServiceNode;

class TcpTransport
{
    private readonly ProtocolCodec $codec;

    public function __construct(
        ?ProtocolCodec $codec = null,
        private readonly ProtocolConfig $config = new ProtocolConfig(),
    ) {
        $this->codec = $codec ?? new ProtocolCodec(new PayloadSigner(new SecurityConfig()));
    }

    public function send(ServiceNode $node, Request $request, float $timeout): Response
    {
        try {
            $packet = Protocol::pack($this->codec->encodeRequest($request));
        } catch (\Throwable $throwable) {
            return $this->failure(
                $request,
                ResponseStatus::TRANSPORT_ERROR,
                'Unable to encode RPC request: ' . $throwable->getMessage(),
            );
        }
        if (strlen($packet) > $this->config->maxPackageSize) {
            return $this->failure(
                $request,
                ResponseStatus::TRANSPORT_ERROR,
                sprintf(
                    'RPC request package exceeds the maximum size of %d bytes.',
                    $this->config->maxPackageSize,
                ),
            );
        }

        $domain = filter_var($node->getIp(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? AF_INET6
            : AF_INET;
        $client = new \Swoole\Coroutine\Socket($domain, SOCK_STREAM, IPPROTO_TCP);
        try {
            if (!$client->setProtocol([
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => $this->config->maxPackageSize,
            ])) {
                return $this->failure(
                    $request,
                    ResponseStatus::TRANSPORT_ERROR,
                    $client->errMsg ?: 'Unable to configure the RPC socket protocol.',
                );
            }
            if (!$client->connect($node->getIp(), $node->getPort(), $timeout)) {
                $status = self::classifySocketFailure($client->errCode, ResponseStatus::CONNECT_TIMEOUT);
                return $this->failure(
                    $request,
                    $status,
                    $client->errMsg ?: ($status === ResponseStatus::CONNECT_TIMEOUT
                        ? 'RPC node connection timed out.'
                        : 'Unable to connect to RPC node.'),
                );
            }
            $sentBytes = $client->sendAll($packet, $timeout);
            if ($sentBytes === false || $sentBytes !== strlen($packet)) {
                return $this->failure(
                    $request,
                    ResponseStatus::TRANSPORT_ERROR,
                    $client->errMsg ?: 'Unable to send the complete RPC request.',
                );
            }
            $responsePacket = $client->recvPacket($timeout);
            if (!is_string($responsePacket) || $responsePacket === '') {
                $status = self::classifySocketFailure($client->errCode, ResponseStatus::SERVER_TIMEOUT);
                return $this->failure(
                    $request,
                    $status,
                    $client->errMsg ?: ($status === ResponseStatus::SERVER_TIMEOUT
                        ? 'RPC response timed out.'
                        : 'RPC connection closed before a response was received.'),
                );
            }
            try {
                return $this->codec->decodeResponse(Protocol::unpack($responsePacket));
            } catch (\Throwable $throwable) {
                return $this->failure(
                    $request,
                    ResponseStatus::RESPONSE_DECODE_ERROR,
                    'Unable to decode RPC response: ' . $throwable->getMessage(),
                );
            }
        } finally {
            $client->close();
        }
    }

    private function failure(Request $request, ResponseStatus $status, string $message): Response
    {
        return (new Response($request->getRequestUUID()))
            ->setStatus($status)
            ->setNodeHealthStatus(NodeHealthStatus::SUSPECT)
            ->setMsg($message);
    }

    private static function classifySocketFailure(int $errorCode, ResponseStatus $timeoutStatus): ResponseStatus
    {
        $timeoutErrors = [];
        foreach (['SOCKET_ETIMEDOUT', 'SOCKET_EAGAIN', 'SOCKET_EWOULDBLOCK', 'SWOOLE_ERROR_SOCKET_POLL_TIMEOUT'] as $constant) {
            if (defined($constant)) {
                $timeoutErrors[] = constant($constant);
            }
        }
        return in_array($errorCode, $timeoutErrors, true)
            ? $timeoutStatus
            : ResponseStatus::TRANSPORT_ERROR;
    }
}
