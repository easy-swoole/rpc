<?php

namespace EasySwoole\Rpc\Utility;

use EasySwoole\Rpc\Exception\RpcException;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Server\ServiceNode;

final class SwooleTableNodeManager extends AbstractNodeManager
{
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
            $this->table->column('service', \Swoole\Table::TYPE_STRING, 128);
            $this->table->column('node', \Swoole\Table::TYPE_STRING, 2048);
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
        try {
            $encodedNode = json_encode($node, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RpcException('Unable to encode service node.', previous: $exception);
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

    public function getNodes(string $serviceName): array
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
            } catch (\JsonException $exception) {
                throw new RpcException('Unable to decode service node.', previous: $exception);
            }
            $nodes[] = ServiceNode::fromArray($node);
        }
        return $nodes;
    }

    private function key(ServiceNode $node): string
    {
        // Swoole Table keys are limited to 63 bytes; SHA-1's 40-byte hex form fits.
        return sha1($node->getServiceName() . "\0" . $node->getNodeId());
    }
}
