# EasySwoole RPC 6.x

基于 Swoole 协程与 TCP 短连接的 RPC 组件。服务端通过 `Service.Module.action` 分发请求，客户端通过节点管理器发现服务节点，并发完成多个调用。

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
    → Service 定位 Module
    → Module 定位 RpcAction
    → Response
```

默认组合：

- `SwooleTableNodeManager`：在本机多个 Swoole Worker 间共享节点；
- `WeightedRandomNodeSelector`：根据 Service 声明的权重选择节点；
- `TcpTransport`：使用带 4 字节长度头的 TCP 短连接传输 JSON。

`Rpc` 构造函数只接收两个聚合对象：`RpcConfig` 保存不可变的运行配置，`RpcDependencies` 保存可替换组件。两者都能省略并自动创建默认对象，只通过 getter 暴露内部实例。

## 1. 定义服务

Service 只声明服务名称、权重并管理 Module，不直接暴露 Action。

```php
<?php

namespace App\Rpc;

use EasySwoole\Rpc\AbstractInterface\AbstractService;

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

}
```

Service 找不到请求指定的 Module 时，会调用可覆写的 `moduleNotFound()`，不会在 Service 层判断 Action。

## 2. 定义 Module

所有远程 Action 都定义在 Module 中。只有标记了 `#[RpcAction]` 的 public 方法才能被调用；Action 是否存在及是否允许调用完全由 `AbstractServiceModule` 处理。

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
    public function getUser(mixed $arg, Request $request): array
    {
        return [
            'id' => (int) $arg['id'],
            'name' => 'EasySwoole',
            'requestId' => $request->getRequestUUID(),
        ];
    }

    #[RpcAction]
    public function detail(mixed $arg): array
    {
        return ['userId' => (int) $arg['id'], 'bio' => 'Hello RPC'];
    }

    // 没有 RpcAction，不可被远程调用。
    public function internalHelper(): string { return 'internal'; }
}
```

调用路径为 `User.Profile.getUser`。Module 找不到 Action 时会调用可覆写的 `actionNotFound()`。

注册 Module：

```php
$userService = new UserService();
$userService->addModule(new ProfileModule());
$rpc->server()->registerService($userService);
```

## 3. 启动 RPC 服务端

### 挂载到 Swoole Server

```php
<?php

use App\Rpc\ProfileModule;
use App\Rpc\UserService;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\RpcConfig;
use EasySwoole\Rpc\Server\DiscoveryConfig;
use EasySwoole\Rpc\Server\ServerConfig;
use Swoole\Server;

require __DIR__ . '/vendor/autoload.php';

$rpc = new Rpc(
    config: new RpcConfig(
        server: new ServerConfig(
            advertiseIp: '127.0.0.1', // 客户端实际连接的地址，不能填写 0.0.0.0
            listenAddress: '0.0.0.0',
            listenPort: 9601,
            nodeName: 'user-rpc-1',
        ),
        discovery: new DiscoveryConfig(
            listen: false,
            broadcast: false,
        ),
    ),
);

$service = new UserService();
$service->addModule(new ProfileModule());
$rpc->server()->registerService($service);

$rpc->server()->setOnException(
    function (Throwable $throwable): void {
        // 只记录没有被 Service 或 Module onException 处理掉的意外异常。
        error_log($throwable->getMessage());
    },
);

$server = new Server('0.0.0.0', 9501, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
$rpc->server()->attachServer($server);
$server->start();
```

Service 和 Module 内部抛出的业务异常由各自的 `onException()` 处理，不会重复通知全局处理器。Server 的全局 `onException` 只接收仍未被业务层处理的意外异常以及协议解析异常，回调只接收 `Throwable`，不参与 Response 构造。

`attachServer()` 会检查主端口和全部 subPort。若 `ServerConfig::listenPort` 已经注册，它会抛出异常，不会擅自覆盖已有 listener 的协议配置和回调；只有端口尚未注册时才调用 `addListener()` 创建 RPC 专用 TCP subPort。它会自动挂载一个 assist-worker。

assist-worker 通过当前 `$server->addProcess()` 直接挂载，不进入 `ProcessManager` 的全局待挂载列表，避免多个 Server 或多个 RPC 实例之间重复添加进程。`$rpc->server()->hasAttach()` 可用于判断当前 RPC Server 是否已经执行过挂载；重复调用 `attachServer()` 会抛出异常。

### 挂载到已有 Server 的子监听

已有 HTTP Server 时只需传入主 Server，RPC subPort 会按配置自动创建：

```php
use Swoole\Http\Server;

$server = new Server('0.0.0.0', 9501);
$rpc->server()->attachServer($server);
$server->start();
```

调用方不再创建或传入 subPort。本机服务节点的上线、心跳和下线由自动挂载的 `AssistWorker` 维护。

## 4. 客户端调用

客户端必须先能从 NodeManager 中取得服务节点。最简单的固定节点示例如下：

`ServiceNode::nodeId` 是节点身份的一部分，不能为空；同一服务下的每个节点必须使用稳定且唯一的 ID。

```php
<?php

use EasySwoole\Rpc\Protocol\Response;
use EasySwoole\Rpc\Rpc;
use EasySwoole\Rpc\RpcDependencies;
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

$rpc = new Rpc(dependencies: new RpcDependencies(nodeManager: $nodeManager));
$client = $rpc->client();

$context = $client->addRequest('User.Profile.getUser');
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
$client->addRequest('User.Profile.getUser')->setArg(['id' => 1001]);
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
use EasySwoole\Rpc\RpcDependencies;

$rpc = new Rpc(
    dependencies: new RpcDependencies(
        failurePolicy: new ConsecutiveFailurePolicy(threshold: 5),
    ),
);
```

自定义策略需要实现 `NodeFailurePolicyInterface`，并返回 `KEEP`、`RETRY_ANOTHER` 或 `OFFLINE`。

## 5. 节点心跳与 UDP 服务发现

`attachServer()` 只挂载一个 `AssistWorker` 进程，但进程内部保持两组独立职责：

- 本机节点生命周期始终启用，负责上线、定时心跳和关闭下线；
- UDP 服务发现仅在开启 `listen` 或 `broadcast` 时启用，负责广播、接收远端节点和广播下线。

本机节点心跳周期属于 `ServerConfig`：

```php
$serverConfig = new ServerConfig(
    heartbeatIntervalMs: 5000,
);
```

只有采用无主化注册中心时，才需要开启 UDP 的 `listen` 或 `broadcast`；使用 Redis、Etcd 等集中式 NodeManager 时可以同时关闭它们：

```php
$discoveryConfig = new DiscoveryConfig(
    listen: false,
    broadcast: false,
);
```

在构造 `Rpc` 时传入 UDP 服务发现配置；`attachServer()` 会自动完成端口预检和 assist-worker 挂载：

```php
use EasySwoole\Rpc\Server\DiscoveryConfig;
use EasySwoole\Rpc\RpcConfig;

$rpc = new Rpc(
    config: new RpcConfig(
        server: $serverConfig,
        discovery: new DiscoveryConfig(
            listen: true,
            listenAddress: '0.0.0.0',
            listenPort: 9701,
            broadcast: true,
            broadcastAddresses: [
                '127.0.0.1:9702',
                '192.168.1.20:9701',
            ],
            broadcastIntervalMs: 5000,
            secret: 'replace-with-a-shared-secret',
        ),
    ),
);

$rpc->server()->attachServer($server);
$server->start();
```

当 `listen=true` 时，`attachServer()` 会先在主进程中尝试 bind UDP 地址。若端口已被其他程序占用、地址不可用或当前用户没有监听权限，会立即抛出异常，避免 assist-worker 启动后反复退出。预检成功后会立即释放探测 Socket，再由 `AssistWorker` 正式监听。

每个参与服务发现的实例必须使用相同的 `secret`。`broadcastAddresses` 应填写其他实例实际监听的 UDP 地址。

节点过期判断属于 NodeManager 的职责。内置 `SwooleTableNodeManager` 默认只返回最近 15 秒内收到过心跳的节点，并在查询时移除过期记录；可通过 `nodeTtlSeconds` 调整：

```php
$nodeManager = new SwooleTableNodeManager(nodeTtlSeconds: 30);
```

自定义 NodeManager 也必须在 `getNodes()` 中只返回当前可用节点。例如 Redis 实现可以依赖键 TTL，Etcd 实现可以依赖 lease，而不需要 `AssistWorker` 识别或清理具体存储。

纯客户端网关可以配置为只监听、不广播，用来接收服务节点：

```php
$rpc = new Rpc(config: new RpcConfig(
    discovery: new DiscoveryConfig(
        listen: true,
        listenPort: 9702,
        broadcast: false,
        secret: 'replace-with-a-shared-secret',
    ),
));
$rpc->server()->attachServer($gatewayServer);
```

`AssistWorker` 继承自 `EasySwoole\Component\Process\AbstractProcess`。本机心跳和 UDP 广播使用不同的定时器；关闭 UDP 时不会创建 Socket 或 UDP 定时器。

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
use EasySwoole\Rpc\RpcDependencies;
use EasySwoole\Rpc\Server\ServiceNode;

final class FirstNodeSelector implements NodeSelectorInterface
{
    public function select(array $nodes): ?ServiceNode
    {
        return $nodes[0] ?? null;
    }
}

$rpc = new Rpc(dependencies: new RpcDependencies(
    nodeSelector: new FirstNodeSelector(),
));
```

默认的 `WeightedRandomNodeSelector` 根据 `serviceWeight()` 生成的节点权重随机选择。

## 8. 自定义节点管理器

需要使用 Redis、Consul 或其他注册中心时，继承 `AbstractNodeManager`：

### fork-safe 实现约定

NodeManager 实例会在 Swoole 主进程中创建，并在 fork 后同时被 Worker 和 assist-worker 使用。因此所有自定义实现都必须允许跨进程 fork：

- 构造函数只能保存配置，不能提前建立 Redis、Etcd、Consul 或其他网络连接；
- 网络客户端、Socket、协程客户端等进程相关资源必须在子进程中延迟创建；
- 必须记录连接所属 PID；发现 `getmypid()` 变化时，丢弃继承来的连接并在当前进程重新连接；
- 不得依赖普通 PHP 成员变量在多个进程间共享状态；需要共享内存时使用 fork 前创建的 `Swoole\Table` 等跨进程设施；
- `online()`、`heartbeat()`、`offline()`、`hibernate()` 和 `getNodes()` 必须支持多个进程并发调用。

Redis 类型的实现可以使用下面的按 PID 建连模式：

```php
use EasySwoole\Rpc\AbstractInterface\AbstractNodeManager;
use EasySwoole\Rpc\RpcDependencies;
use EasySwoole\Rpc\Server\ServiceNode;

final class RedisNodeManager extends AbstractNodeManager
{
    private ?\Redis $redis = null;
    private ?int $connectionPid = null;

    public function __construct(private readonly array $config)
    {
        // 这里只保存配置，不建立连接。
    }

    private function redis(): \Redis
    {
        $pid = getmypid();
        if ($this->redis === null || $this->connectionPid !== $pid) {
            // fork 后不能继续使用父进程创建的连接。
            $this->redis = new \Redis();
            $this->redis->connect($this->config['host'], $this->config['port']);
            $this->connectionPid = $pid;
        }
        return $this->redis;
    }

    public function online(ServiceNode $node): bool
    {
        // 使用 $this->redis() 写入节点。
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

$rpc = new Rpc(dependencies: new RpcDependencies(
    nodeManager: new RedisNodeManager([
        'host' => '127.0.0.1',
        'port' => 6379,
    ]),
));
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
├── Server/             Server、ServiceNode、配置及 AssistWorker
└── Utility/            Swoole Table 节点管理和默认节点选择器
```
