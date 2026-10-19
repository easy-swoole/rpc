<?php

namespace EasySwoole\Rpc\Utility;

use EasySwoole\Rpc\Exception\RpcException;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Server\ServiceNode;

final class SwooleTableNodeManager extends AbstractNodeManager
{
    public const SERVICE_COLUMN_BYTES = 64;
    public const NODE_COLUMN_BYTES = 4096;

    private \Swoole\Table $table;

    public function __construct(
        int $size = 1024,
        ?\Swoole\Table $table = null,
        private readonly int $nodeTtlSeconds = 15,
    )
    {
        if ($nodeTtlSeconds < 1) {
            throw new RpcException('Node TTL must be at least 1 second.');
        }
        $this->table = $table ?? new \Swoole\Table($size);
        if ($table === null) {
            $this->table->column('service', \Swoole\Table::TYPE_STRING, self::SERVICE_COLUMN_BYTES);
            $this->table->column('node', \Swoole\Table::TYPE_STRING, self::NODE_COLUMN_BYTES);
            $this->table->column('heartbeat', \Swoole\Table::TYPE_INT, 8);
            $this->table->create();
        }
    }

    public function heartbeat(ServiceNode $node): bool
    {
        return $this->online($node);
    }

    public function online(ServiceNode $node): bool
    {
        if (strlen($node->getServiceName()) > self::SERVICE_COLUMN_BYTES) {
            return false;
        }
        try {
            $encodedNode = json_encode($node, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }
        if (strlen($encodedNode) > self::NODE_COLUMN_BYTES) {
            return false;
        }
        return $this->table->set($this->key($node), [
            'service' => $node->getServiceName(),
            'node' => $encodedNode,
            'heartbeat' => time(),
        ]);
    }

    public function offline(ServiceNode $node): bool
    {
        $this->table->del($this->key($node));
        return true;
    }

    public function getNodes(string $serviceName): array|false
    {
        $nodes = [];
        $deadline = time() - $this->nodeTtlSeconds;
        foreach ($this->table as $key => $row) {
            if ($row['heartbeat'] < $deadline) {
                $this->table->del((string) $key);
                continue;
            }
            if ($row['service'] !== $serviceName) {
                continue;
            }
            try {
                $node = json_decode($row['node'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($node)) {
                    $this->table->del((string) $key);
                    continue;
                }
                $nodes[] = ServiceNode::fromArray($node);
            } catch (\JsonException | RpcException) {
                $this->table->del((string) $key);
            }
        }
        return $nodes;
    }

    private function key(ServiceNode $node): string
    {
        // Swoole Table keys are limited to 63 bytes; SHA-1's 40-byte hex form fits.
        return sha1($node->getServiceName() . "\0" . $node->getNodeId());
    }
}
