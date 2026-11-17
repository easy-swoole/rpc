<?php

namespace EasySwoole\Rpc\Server;

use EasySwoole\Rpc\Exception\RpcException;
use Swoole\Table;

/** @internal */
final class TcpProcessHeartbeats
{
    public const INTERVAL_MS = 100;
    private const TTL_MS = 1500;

    private readonly Table $table;

    public function __construct(int $processCount)
    {
        // 每个进程只更新自己的 index。异常退出的记录由读取方按时间过滤。
        $this->table = new Table($processCount);
        $this->table->column('lastSeen', Table::TYPE_INT, 8);
        if (!$this->table->create()) {
            throw new RpcException('Unable to create the RPC TCP process heartbeat table.');
        }
    }

    public function refresh(int $index): void
    {
        $this->table->set((string) $index, ['lastSeen' => intdiv(hrtime(true), 1_000_000)]);
    }

    public function remove(int $index): void
    {
        $this->table->del((string) $index);
    }

    public function hasLiveProcess(): bool
    {
        $cutoff = intdiv(hrtime(true), 1_000_000) - self::TTL_MS;
        foreach ($this->table as $row) {
            if ($row['lastSeen'] > $cutoff) {
                return true;
            }
        }
        return false;
    }
}
