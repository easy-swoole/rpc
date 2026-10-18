# EasySwoole RPC 6.x

基于 Swoole 协程与 TCP 短连接的 RPC 组件。服务端通过 `Service.action` 或 `Service.Module.action` 分发请求，客户端通过节点管理器发现服务节点，并发完成多个调用。

## 环境要求

- PHP >= 8.1
- Swoole >= 5.1

```bash
composer require easyswoole/rpc:^6.0
```

## 调用流程

```text
Client.addRequest()
    → NodeManager.getNodes(serviceName)
    → NodeSelector.select(nodes)
    → TcpTransport.send()
    → Swoole TCP listener
    → Server.handle()
    → Service / Module RpcAction
    → Response
```

默认组合：

- `SwooleTableNodeManager`：在本机多个 Swoole Worker 间共享节点；
- `WeightedRandomNodeSelector`：根据 Service 声明的权重选择节点；
- `TcpTransport`：使用带 4 字节长度头的 TCP 短连接传输 JSON。

## 1. 定义服务

只有标记了 `#[RpcAction]` 的 public 方法才能被远程调用。未标记的方法即使是 public，也不会暴露。

```php
<?php

namespace App\Rpc;

use EasySwoole\Rpc\AbstractInterface\AbstractService;
use EasySwoole\Rpc\Attribute\RpcAction;
use EasySwoole\Rpc\Protocol\Request;

final class UserService extends AbstractService
{
    public function serviceName(): string
    {
        return 'User';
    }

    // 默认值为 10。权重越大，被选中的概率越高。
    public function serviceWeight(): int
    {
        return 20;
    }

    #[RpcAction]
    public function getUser(mixed $arg, Request $request): array
    {
        return [
            'id' => (int) $arg['id'],
            'name' => 'EasySwoole',
            'requestId' => $request->getRequestUUID(),
        ];
    }

    // 没有 RpcAction，不可被远程调用。
    public function internalHelper(): string
    {
        return 'internal';
    }
}
```

调用路径：

```text
User.getUser
```

## 2. 可选：定义 Module

服务可以通过 Module 继续分组。例如 `User.Profile.detail`：

```php
<?php

namespace App\Rpc;

use EasySwoole\Rpc\AbstractInterface\AbstractServiceModule;
use EasySwoole\Rpc\Attribute\RpcAction;
use EasySwoole\Rpc\Protocol\Request;

final class ProfileModule extends AbstractServiceModule
{
    public function moduleName(): string
    {
        return 'Profile';
    }

    #[RpcAction]
    public function detail(mixed $arg, Request $request): array
    {
        return ['userId' => (int) $arg['id'], 'bio' => 'Hello RPC'];
    }
}
```

注册 Module：

```php
$userService = new UserService();
$userService->addModule(new ProfileModule());
$rpc->server()->registerService($userService);
```

## 3. 启动 RPC 服务端

### 挂载到独立 TCP Server

```php
<?php

use App\Rpc\ProfileModule;
use App\Rpc\UserService;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Server\ServerConfig;
use Swoole\Server;

require __DIR__ . '/vendor/autoload.php';

$rpc = new Rpc(serverConfig: new ServerConfig(
    advertiseIp: '127.0.0.1', // 客户端实际连接的地址，不能填写 0.0.0.0
    listenAddress: '0.0.0.0',
    listenPort: 9601,
    nodeName: 'user-rpc-1',
));

$service = new UserService();
$service->addModule(new ProfileModule());
$rpc->server()->registerService($service);

$server = new Server('0.0.0.0', 9601, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
$rpc->server()->attach($server);
$server->start();
```

`attach()` 会校验 Swoole listener 的端口是否等于 `ServerConfig::listenPort`。

### 挂载到已有 Server 的子监听

已有 HTTP Server 时，可通过 `addListener()` 单独增加 RPC TCP 端口：

```php
use Swoole\Http\Server;

$server = new Server('0.0.0.0', 9501);
$rpcListener = $server->addListener('0.0.0.0', 9601, SWOOLE_SOCK_TCP);

if (!$rpcListener instanceof Swoole\Server\Port) {
    throw new RuntimeException('Unable to create RPC listener.');
}

$rpc->server()->attach($rpcListener);
$server->start();
```

`attach()` 只负责 TCP 协议配置、请求监听和本机节点上线，不负责创建 assist-worker。

## 4. 客户端调用

客户端必须先能从 NodeManager 中取得服务节点。最简单的固定节点示例如下：

```php
<?php

use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\Server\ServiceNode;
use EasySwoole\Rpc\Utility\SwooleTableNodeManager;

require __DIR__ . '/vendor/autoload.php';

$nodeManager = new SwooleTableNodeManager();
$nodeManager->online(new ServiceNode(
    serviceName: 'User',
    nodeId: 'user-rpc-1',
    nodeName: 'user-rpc-1',
    nodeWeight: 20,
    ip: '127.0.0.1',
    port: 9601,
));

$rpc = new Rpc(nodeManager: $nodeManager);
$client = $rpc->client();

$context = $client->addRequest('User.getUser');
$context
    ->setArg(['id' => 1001])
    ->setClientArg(['traceId' => 'trace-001'])
    ->enableRetry(1)
    ->setOnSuccess(function (Response $response): void {
        var_dump($response->getResult());
    })
    ->setOnFail(function (Response $response): void {
        var_dump($response->getStatus(), $response->getMsg());
    });

$responses = $client->exec(timeout: 3.0);
$response = $responses[0];
```

Module 调用使用三段路径：

```php
$client = $rpc->client();
$client->addRequest('User.Profile.detail')->setArg(['id' => 1001]);
$response = $client->exec()[0];
```

### 并发调用多个服务

同一个 Client 中加入的请求会通过 Swoole 协程并发执行，返回顺序与添加顺序一致：

```php
$client = $rpc->client();
$client->addRequest('User.getUser')->setArg(['id' => 1001]);
$client->addRequest('User.Profile.detail')->setArg(['id' => 1001]);

[$userResponse, $profileResponse] = $client->exec(timeout: 3.0);
```

每次 `exec()` 后请求队列会被清空。需要发起下一批调用时重新调用 `addRequest()`。

### 节点故障策略

客户端不会因为一次业务异常就把节点全局下线。默认的 `ConsecutiveFailurePolicy` 行为如下：

- `STATUS_SERVICE_ERROR`：视为业务执行异常，保留节点；
- 连接失败、服务响应超时或读包超时：记录连续失败，默认累计 3 次后下线；
- `STATUS_SERVICE_SHUTDOWN`：服务明确关闭，立即下线；
- 低于下线阈值时，如果启用了重试，会重新选择节点，但不会修改全局节点表；
- 节点成功响应后，连续网络失败计数会被清零。

可以调整连续失败阈值，或注入自己的策略：

```php
use EasySwoole\Rpc\Client\ConsecutiveFailurePolicy;

$rpc = new Rpc(
    failurePolicy: new ConsecutiveFailurePolicy(threshold: 5),
);
```

自定义策略需要实现 `NodeFailurePolicyInterface`，并返回 `KEEP`、`RETRY_ANOTHER` 或 `OFFLINE`。

## 5. UDP 服务发现与 assist-worker

固定节点适合开发和简单部署。多节点部署可启用独立 assist-worker：

- 进程启动时将本机服务节点标记为上线；
- 通过独立定时器周期刷新本机节点心跳；
- 进程关闭时将本机服务节点标记为下线；
- 通过 UDP 广播本机服务节点；
- 监听其他服务广播并更新本机 NodeManager；
- 清理超时节点；
- 进程退出时广播节点下线。

本机节点的上线、心跳和下线属于 assist-worker 的基础职责，与 UDP 无关。只有采用无主化注册中心时，才需要开启 UDP 的 `listen` 或 `broadcast`；使用 Redis、Etcd 等集中式 NodeManager 时可以同时关闭它们：

```php
new DiscoveryConfig(
    listen: false,
    broadcast: false,
    heartbeatIntervalMs: 5000,
);
```

assist-worker 必须在所有服务注册完成后创建，并由调用方添加到主 Swoole Server：

```php
use EasySwoole\Component\Process\Manager as ProcessManager;
use EasySwoole\Rpc\Server\DiscoveryConfig;

$assistWorker = $rpc->server()->createAssistWorker(new DiscoveryConfig(
    listen: true,
    listenAddress: '0.0.0.0',
    listenPort: 9701,
    broadcast: true,
    broadcastAddresses: [
        '127.0.0.1:9702',
        '192.168.1.20:9701',
    ],
    heartbeatIntervalMs: 5000,
    nodeTtlSeconds: 15,
    secret: 'replace-with-a-shared-secret',
));

ProcessManager::getInstance()
    ->addProcess($assistWorker)
    ->attachToServer($server);
$server->start();
```

每个参与服务发现的实例必须使用相同的 `secret`。`broadcastAddresses` 应填写其他实例实际监听的 UDP 地址。

纯客户端网关也可以创建只监听、不广播的 assist-worker，用来接收服务节点：

```php
use EasySwoole\Component\Process\Manager as ProcessManager;

$processManager = ProcessManager::getInstance();
$assistWorker = $rpc->server()->createAssistWorker(new DiscoveryConfig(
    listen: true,
    listenPort: 9702,
    broadcast: false,
    secret: 'replace-with-a-shared-secret',
));

$processManager->addProcess($assistWorker);
$processManager->attachToServer($gatewayServer);
```

`AssistWorker` 继承自 `EasySwoole\Component\Process\AbstractProcess`。进程的注册、PID 信息、组件心跳和退出生命周期均由 `easyswoole/component` 的进程管理器统一维护。进入 `onShutDown()` 时，它会广播 OFFLINE 消息，并将本机注册的服务节点从 NodeManager 下线。

## 6. 服务节点生命周期

Service 只负责请求处理，不持有 ServiceNode 或 NodeManager。节点状态由 Server 协调，并最终交给 `AbstractNodeManager`：

```php
$rpc->server()->onlineService('User');
$rpc->server()->heartbeatService('User');
$rpc->server()->hibernateService('User');
$rpc->server()->offlineService('User');
```

获取节点信息：

```php
$nodes = $rpc->getNodeManager()->getNodes('User');
```

## 7. 自定义节点选择策略

NodeManager 不负责选择节点。自定义负载策略需要实现 `NodeSelectorInterface`：

```php
use EasySwoole\Rpc\AbstractInterface\NodeSelectorInterface;
use EasySwoole\Rpc\Server\ServiceNode;

final class FirstNodeSelector implements NodeSelectorInterface
{
    public function select(array $nodes): ?ServiceNode
    {
        return $nodes[0] ?? null;
    }
}

$rpc = new Rpc(nodeSelector: new FirstNodeSelector());
```

默认的 `WeightedRandomNodeSelector` 根据 `serviceWeight()` 生成的节点权重随机选择。

## 8. 自定义节点管理器

需要使用 Redis、Consul 或其他注册中心时，继承 `AbstractNodeManager`：

```php
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\Server\ServiceNode;

final class RedisNodeManager extends AbstractNodeManager
{
    public function online(ServiceNode $node): bool
    {
        // 写入 Redis
        return true;
    }

    public function heartbeat(ServiceNode $node): bool
    {
        // 刷新 TTL
        return true;
    }

    public function hibernate(ServiceNode $node): bool
    {
        // 暂停节点
        return true;
    }

    public function offline(ServiceNode $node): bool
    {
        // 删除节点
        return true;
    }

    public function getNodes(string $serviceName): array
    {
        // 返回 list<ServiceNode>
        return [];
    }
}

$rpc = new Rpc(nodeManager: new RedisNodeManager());
```

## 9. 响应状态

`Response::isSuccess()` 仅在状态为 `STATUS_OK` 时返回 `true`。

| 状态 | 含义 |
| --- | --- |
| `STATUS_OK` | 调用成功 |
| `STATUS_NOT_AVAILABLE_NODE` | 没有可用节点 |
| `STATUS_CONNECT_TIMEOUT` | 节点连接失败或超时 |
| `STATUS_SERVER_TIMEOUT` | 服务响应超时 |
| `STATUS_PACKAGE_READ_TIMEOUT` | 服务端读取请求超时 |
| `STATUS_ILLEGAL_PACKAGE` | 数据包或请求格式非法 |
| `STATUS_SERVICE_SHUTDOWN` | 服务已停止接收请求 |
| `STATUS_SERVICE_NOT_EXIST` | 服务不存在 |
| `STATUS_MODULE_NOT_EXIST` | Module 不存在 |
| `STATUS_ACTION_NOT_EXIST` | Action 不存在或未标记 `RpcAction` |
| `STATUS_SERVICE_ERROR` | 服务执行异常 |

## 10. 运行测试

```bash
composer install
composer test
```

## 项目结构

```text
src/
├── AbstractInterface/  抽象服务、节点管理器和扩展接口
├── Attribute/          RpcAction 等 PHP Attribute
├── Client/             客户端、请求上下文和 TCP Transport
├── Protocol/           Request、Response 和长度头协议
├── Server/             Server、ServiceNode、配置及 assist-worker
└── Utility/            Swoole Table 节点管理和默认节点选择器
```
