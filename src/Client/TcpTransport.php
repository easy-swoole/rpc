<?php

namespace EasySwoole\Rpc\Client;

use EasySwoole\Rpc\Exception\RpcException;
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
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new RpcException('RPC timeout must be greater than zero.');
        }
        $deadline = microtime(true) + $timeout;
        // 编码失败属于调用或程序异常，不应伪装成网络传输失败。
        $packet = Protocol::pack($this->codec->encodeRequest($request));
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
        // 部分 PHP/Swoole 构建没有 IPPROTO_TCP；传 0 由系统为 SOCK_STREAM 自动选择 TCP。
        $client = new \Swoole\Coroutine\Socket($domain, SOCK_STREAM, 0);
        try {
            // 客户端与服务端统一使用 4 字节网络序长度头，recvPacket() 会读取完整响应帧。
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
            $remaining = self::remainingTimeout($deadline);
            if ($remaining === null) {
                return $this->deadlineExceeded($request);
            }
            if (!$client->connect($node->getIp(), $node->getPort(), $remaining)) {
                $status = self::classifySocketFailure($client->errCode, ResponseStatus::CONNECT_TIMEOUT);
                return $this->failure(
                    $request,
                    $status,
                    $client->errMsg ?: ($status === ResponseStatus::CONNECT_TIMEOUT
                        ? 'RPC node connection timed out.'
                        : 'Unable to connect to RPC node.'),
                );
            }
            $remaining = self::remainingTimeout($deadline);
            if ($remaining === null) {
                return $this->deadlineExceeded($request);
            }
            $sentBytes = $client->sendAll($packet, $remaining);
            // TCP send() 可能只写入部分数据，sendAll() 才能保证完整报文被发送。
            if ($sentBytes === false || $sentBytes !== strlen($packet)) {
                return $this->failure(
                    $request,
                    ResponseStatus::TRANSPORT_ERROR,
                    $client->errMsg ?: 'Unable to send the complete RPC request.',
                );
            }
            $remaining = self::remainingTimeout($deadline);
            if ($remaining === null) {
                return $this->deadlineExceeded($request);
            }
            $responsePacket = $client->recvPacket($remaining);
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

    private function deadlineExceeded(Request $request): Response
    {
        return $this->failure($request, ResponseStatus::SERVER_TIMEOUT, 'RPC request deadline exceeded.');
    }

    private static function remainingTimeout(float $deadline): ?float
    {
        $remaining = $deadline - microtime(true);
        return $remaining > 0 ? $remaining : null;
    }

    private static function classifySocketFailure(int $errorCode, ResponseStatus $timeoutStatus): ResponseStatus
    {
        // 只有明确的超时错误才映射为超时，其余网络失败统一保留为 TRANSPORT_ERROR。
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
