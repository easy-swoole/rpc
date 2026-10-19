<?php

namespace EasySwoole\Rpc\AbstractInterface;

use EasySwoole\Rpc\Server\ServiceNode;

/**
 * NodeManager 必须支持在 Swoole fork 后安全使用。
 *
 * 同一个实例会在父进程中创建，再由业务 Worker 和 RPC 自定义进程继承。共享内存资源可以在
 * fork 前初始化；Redis/Etcd 客户端、Socket、协程客户端等进程绑定资源必须在子进程中延迟连接，
 * 并在当前 PID 与资源所属 PID 不一致时重新创建。
 */
abstract class AbstractNodeManager
{
    /** 返回节点过期秒数；不使用 TTL 的实现返回 null。 */
    public function nodeTtlSeconds(): ?int { return null; }

    /**
     * 注册中心暂时不可用等运行失败应返回 false，而不是抛出异常。调用方会记录失败并继续正常的
     * 心跳或重试周期，使节点能够在网络恢复后自动恢复。
     */
    abstract public function heartbeat(ServiceNode $node): bool;
    abstract public function offline(ServiceNode $node): bool;
    abstract public function online(ServiceNode $node): bool;

    /** @return list<ServiceNode>|false 空数组表示查询成功但没有节点；false 表示注册中心查询失败。 */
    abstract public function getNodes(string $serviceName): array|false;

}
