<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use EasySwoole\Component\Process\Socket\TcpProcessConfig;
use EasySwoole\Rpc\Config\ProtocolConfig;
use EasySwoole\Rpc\Protocol\PayloadSigner;
use EasySwoole\Rpc\Protocol\Protocol;
use EasySwoole\Rpc\Protocol\RequestFrame;
use Swoole\Coroutine\Socket;
use Swoole\Timer;

/** @internal */
final class RpcTcpProcess extends AbstractTcpProcess
{
    public function __construct(
        TcpProcessConfig $config,
        private readonly int $index,
        private readonly TcpProcessHeartbeats $heartbeats,
        private readonly ProtocolConfig $protocolConfig,
        private readonly PayloadSigner $signer,
        private readonly ServerRequestHandler $handler,
    ) {
        parent::__construct($config);
    }

    public function run($arg): void
    {
        $timer = Timer::tick(TcpProcessHeartbeats::INTERVAL_MS, function (): void {
            // 父类只在 bind 和 listen 成功后设置 socketServer。
            if (!$this->isShutDown && isset($this->socketServer)) {
                $this->heartbeats->refresh($this->index);
            }
        });
        try {
            parent::run($arg);
        } finally {
            Timer::clear($timer);
            $this->heartbeats->remove($this->index);
        }
    }

    public function onAccept(Socket $socket): void
    {
        try {
            if ($this->isShutDown) {
                return;
            }
            $deadline = hrtime(true) + $this->protocolConfig->requestReadTimeoutMs * 1_000_000;
            $header = $this->read($socket, RequestFrame::HEADER_BYTES, $deadline);
            if ($header === null) {
                return;
            }
            $frame = RequestFrame::inspect($header, $this->protocolConfig->maxPackageSize, $this->signer);
            if ($frame === null) {
                return;
            }
            $payload = $this->read($socket, $frame['length'], $deadline);
            if ($payload === null) {
                return;
            }
            $validHash = hash_equals($frame['hash'], hash('sha256', $payload, true));
            if (hrtime(true) >= $deadline) {
                return;
            }
            $response = $validHash
                ? $this->handler->handlePacket($payload)
                : $this->handler->illegalPackage('body hash mismatch');
            // 短连接上的对端可能已结束等待。
            $socket->sendAll(Protocol::pack($response), $this->protocolConfig->requestReadTimeoutMs / 1000);
        } finally {
            $socket->close();
        }
    }

    protected function onShutDown(): void
    {
        $this->heartbeats->remove($this->index);
        parent::onShutDown();
    }

    private function read(Socket $socket, int $length, int|float $deadline): ?string
    {
        $remaining = ($deadline - hrtime(true)) / 1_000_000_000;
        if ($remaining <= 0) {
            return null;
        }
        $data = $socket->recvAll($length, $remaining);
        return is_string($data) && strlen($data) === $length && hrtime(true) < $deadline ? $data : null;
    }
}
