<?php

namespace EasySwoole\Rpc\Utility;

use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Server\ServiceNode;

final class SwooleTableNodeManager extends AbstractNodeManager
{
    private \Swoole\Table $table;

    public function __construct(int $size = 1024, ?\Swoole\Table $table = null)
    {
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
        return $this->table->set($this->key($node), [
            'service' => $node->getServiceName(),
            'node' => json_encode($node, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'heartbeat' => time(),
        ]);
    }

    public function offline(ServiceNode $node): bool
    {
        $this->table->del($this->key($node));
        return true;
    }

    public function hibernate(ServiceNode $node): bool
    {
        return $this->offline($node);
    }

    public function getNodes(string $serviceName): array
    {
        $nodes = [];
        foreach ($this->table as $row) {
            if ($row['service'] !== $serviceName) {
                continue;
            }
            $nodes[] = ServiceNode::fromArray(json_decode($row['node'], true, 512, JSON_THROW_ON_ERROR));
        }
        return $nodes;
    }

    public function prune(int $maxIdleSeconds): int
    {
        $removed = 0;
        $deadline = time() - $maxIdleSeconds;
        foreach ($this->table as $key => $row) {
            if ($row['heartbeat'] < $deadline && $this->table->del((string) $key)) {
                $removed++;
            }
        }
        return $removed;
    }

    private function key(ServiceNode $node): string
    {
        // Swoole Table keys are limited to 63 bytes; SHA-1's 40-byte hex form fits.
        return sha1($node->getServiceName() . "\0" . $node->getNodeId());
    }
}
