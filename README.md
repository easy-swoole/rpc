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
Client.buildRequest().addCall()
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

`Rpc` 构造函数只接收两个聚合对象：`RpcConfig` 保存不可变的运行配置，`RpcDependencies` 保存可替换的 NodeManager、NodeSelector 和 NodeFailurePolicy。两者都能省略并自动创建默认对象，只通过 getter 暴露内部实例。

客户端与服务端内部职责已分开：`Client` 只管理调用批次，`RequestExecutor` 负责单次调用、重试与节点策略，`TcpTransport` 负责协议序列化和网络传输；服务端的 `Server` 只是对外门面，服务登记与冻结由 `ServiceRegistry` 管理，请求分发由 `ServerRequestHandler` 管理，Swoole listener 和 assist-worker 的挂载由 `SwooleServerAttacher` 管理。上述内部协作对象不会通过 `Server` 暴露。

客户端 Transport 不是扩展点。`Client` 根据安全配置和协议配置在内部固定创建与 `attachServer()` 完全对应的 `TcpTransport`，统一使用 TCP 短连接、4 字节长度头、`ProtocolCodec` 和相同的签名规则。`RpcDependencies` 不接受 Transport，`Client` 的构造函数也不接收 Transport；如果未来增加 HTTP 或 WebSocket，应同时实现对应的客户端与服务端协议，而不是只替换客户端发送逻辑。

## 1. 定义服务

Service 只声明服务名称、权重并管理 Module，不直接暴露 Action。

```php
<?php

namespace App\Rpc;

use EasySwoole\Rpc\AbstractInterface\AbstractService;

final class UserService extends AbstractService
{
    // serviceName 会进入调用路径，并作为节点分组键使用。
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
    // 完整调用路径的第二段：User.Profile.getUser。
    public function moduleName(): string
    {
        return 'Profile';
    }

    // 只有带 RpcAction 的 public 方法才能被远程调用。
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
// 必须先完成所有 Module 装配，再注册 Service。
$userService->addModule(new ProfileModule());
// 注册后 Service 会被冻结，不能再增加 Module。
$rpc->server()->registerService($userService);
```

Service 注册后由 `ServiceRegistry` 统一冻结，不能继续添加 Module；重复的 `moduleName()` 也会在注册阶段抛出异常。所有 Module 必须在 `registerService()` 前完成装配。

Action 通常直接返回业务结果，由框架写入当前 Response。如果需要直接返回 `Response`，其中的 `requestUUID` 必须与当前 `Request::getRequestUUID()` 完全一致；否则框架会按业务服务异常处理，避免破坏客户端的请求响应关联校验。

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
            listenAddress: '0.0.0.0', // RPC TCP listener 的绑定地址
            listenPort: 9601,         // attachServer 会创建并监听这个 subPort
            nodeName: 'user-rpc-1',   // 便于日志和运维识别
        ),
        discovery: new DiscoveryConfig(
            // 使用固定节点或集中式注册中心时，可以关闭 UDP 发现。
            listen: false,
            broadcast: false,
        ),
    ),
);

$service = new UserService();
$service->addModule(new ProfileModule());
// 必须在 attachServer() 前注册；attach 开始后 Registry 将被冻结。
$rpc->server()->registerService($service);

$rpc->server()->setOnException(
    function (Throwable $throwable): void {
        // 只记录没有被 Service 或 Module onException 处理掉的意外异常。
        error_log($throwable->getMessage());
    },
);

$server = new Server('0.0.0.0', 9501, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
// 传主 Server 即可，RPC listener 与 AssistWorker 会自动挂载。
$rpc->server()->attachServer($server);
$server->start();
```

Service 和 Module 内部抛出的业务异常由各自的 `onException()` 处理，不会重复通知全局处理器。默认实现通过受保护的 `trigger_error()` 将异常详情交给服务端日志或 error handler；即使应用的 error handler 抛出异常，也不会影响 RPC 响应。客户端只会收到 `ResponseStatus::SERVICE_ERROR` 和统一的 `Server error.`，不会暴露异常消息、文件和行号。业务层可以重写 `onException()` 自定义处理。非法数据包和请求格式错误属于可预期的客户端输入，只返回 `ResponseStatus::ILLEGAL_PACKAGE` 和 `Illegal RPC request.`，同样不会通知全局处理器。Server 的全局 `onException` 只接收仍未被业务层处理的意外异常，回调只接收 `Throwable`，不参与 Response 构造。

`attachServer()` 会检查主端口和全部 subPort。若 `ServerConfig::listenPort` 已经注册，它会抛出异常，不会擅自覆盖已有 listener 的协议配置和回调；只有端口尚未注册时才调用 `addListener()` 创建 RPC 专用 TCP subPort。它会自动挂载一个 assist-worker。

TCP receive 回调具有最外层保护：非法长度帧和非法请求只返回 `ResponseStatus::ILLEGAL_PACKAGE`，不会进入全局错误回调；响应发送失败或回调内未预期错误会通知 `onRuntimeError`；连接始终在 `finally` 中关闭。

assist-worker 通过当前 `$server->addProcess()` 直接挂载，不进入 `ProcessManager` 的全局待挂载列表，避免多个 Server 或多个 RPC 实例之间重复添加进程。`attachServer()` 先完成端口、UDP 和进程对象预检，再统一提交 listener 与进程挂载。状态可通过 `getAttachStatus()` 获取，包含 `PENDING`、`ATTACHING`、`ATTACHED` 和 `FAILED`；`hasAttach()` 仅在状态为 `ATTACHED` 时返回 `true`。每个 RPC Server 只允许尝试挂载一次，失败后不能在可能存在部分外部资源的同一个实例上重试。

| ServerAttachStatus | 含义 |
| --- | --- |
| `PENDING` | 尚未执行 `attachServer()`，允许注册 Service |
| `ATTACHING` | 正在执行预检和资源提交，不允许再修改 Service |
| `ATTACHED` | listener 与 AssistWorker 已成功挂载 |
| `FAILED` | 挂载失败；当前 RPC Server 实例不可重试，应重建 Swoole Server 和 RPC 实例 |

### 挂载到已有 Server 的子监听

已有 HTTP Server 时只需传入主 Server，RPC subPort 会按配置自动创建：

```php
use Swoole\Http\Server;

$server = new Server('0.0.0.0', 9501);
// 不要提前创建 RPC subPort；目标端口已存在时会拒绝挂载。
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
// 固定节点仅用于演示；生产环境通常由 AssistWorker 或注册中心维护。
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

$request = $client->buildRequest();
$context = $request->addCall('User.Profile.getUser');
$context
    ->setArg(['id' => 1001])                   // 传给 Action 的业务参数
    ->setClientArg(['traceId' => 'trace-001']) // 链路信息等客户端附加参数
    ->enableRetry(1)                           // 失败后最多再选节点重试一次
    ->setOnSuccess(function (Response $response): void {
        var_dump($response->getResult());
    })
    ->setOnFail(function (Response $response): void {
        var_dump($response->getStatus(), $response->getMsg());
    });

$responses = $request->exec(timeout: 3.0); // timeout 必须大于 0；同一批次中的调用会并发执行
$response = $responses[0];
```

Module 调用使用三段路径：

```php
$client = $rpc->client();
$request = $client->buildRequest();
$request->addCall('User.Profile.detail')->setArg(['id' => 1001]);
$response = $request->exec()[0];
```

### 并发调用多个服务

同一个 RequestBatch 中加入的调用会通过 Swoole 协程并发执行，返回顺序与添加顺序一致：

```php
$client = $rpc->client();
$request = $client->buildRequest();
$request->addCall('User.Profile.getUser')->setArg(['id' => 1001]);
$request->addCall('User.Profile.detail')->setArg(['id' => 1001]);

[$userResponse, $profileResponse] = $request->exec(timeout: 3.0);
```

`Client` 本身不再保存请求队列。每次 `buildRequest()` 都会创建一个独立的 `RequestBatch`，所以同一个 Client 可以在多个协程中并发构建和执行不同批次。同一个批次执行期间不能继续 `addCall()`，也不能被并发执行。

`exec()` 开始时会立即取走当前调用队列，即使回调或扩展组件抛出异常也不会重复执行旧调用。每个调用协程的意外异常会先被收集，所有协程结束后由 `exec()` 重新抛出第一个异常，不会静默丢失 Response。调用路径会在 `addCall()` 时立即校验。

### 节点故障策略

客户端不会因为一次业务异常就把节点全局下线。默认的 `ConsecutiveFailurePolicy` 行为如下：

- `ResponseStatus::SERVICE_ERROR`：视为业务执行异常，保留节点；
- 连接失败、服务响应超时或读包超时：记录连续失败，默认累计 3 次后下线；
- `ResponseStatus::SERVICE_SHUTDOWN`：服务明确关闭，立即下线；
- 低于下线阈值时，如果启用了重试，会重新选择节点，但不会修改全局节点表；
- 节点成功响应后，连续网络失败计数会被清零。

可以调整连续失败阈值，或注入自己的策略：

```php
use EasySwoole\Rpc\Utility\ConsecutiveFailurePolicy;
use EasySwoole\Rpc\RpcDependencies;

$rpc = new Rpc(
    dependencies: new RpcDependencies(
        // 连续 5 次可疑故障后下线；HEALTHY 响应会清空已有计数。
        failurePolicy: new ConsecutiveFailurePolicy(threshold: 5),
    ),
);
```

RPC 还提供全局的后台运行错误回调。它负责接收 assist-worker 中无法同步抛回调用方的节点上线、心跳、下线以及 UDP 监听和广播错误。回调必须在 `attachServer()` 前设置；回调自身抛出的异常会被隔离，不会终止 assist-worker。

```php
$rpc->setOnRuntimeError(
    function (string $message): void {
        // Closure 会随 Swoole fork 进入 assist-worker；其中使用的资源需由调用方保证支持 fork。
        logger()->error($message);
    }
);
```

消息会直接说明节点上线、心跳、下线或 UDP 监听、广播中的具体失败位置。NodeManager 返回 `false` 只表示本次操作失败：流程不会抛异常或停止，后续心跳仍会继续尝试。自定义 NodeManager 遇到注册中心暂时断网等运行失败时也应返回 `false`，不应抛异常。配置错误、端口冲突和 `attachServer()` 失败仍直接抛出 `RpcException`。

自定义策略需要实现 `NodeFailurePolicyInterface`，并返回 `KEEP`、`RETRY_ANOTHER` 或 `OFFLINE`。

## 5. 节点心跳与 UDP 服务发现

`attachServer()` 只挂载一个 `AssistWorker` 进程，但进程内部保持两组独立职责：

- 本机节点生命周期始终启用，负责上线、定时心跳和关闭下线；
- UDP 服务发现仅在开启 `listen` 或 `broadcast` 时启用，负责广播、接收远端节点和广播下线。

本机节点心跳周期属于 `ServerConfig`：

```php
$serverConfig = new ServerConfig(
    // AssistWorker 刷新本机全部 Service 节点的周期。
    heartbeatIntervalMs: 5000,
);
```

只有采用无主化注册中心时，才需要开启 UDP 的 `listen` 或 `broadcast`；使用 Redis、Etcd 等集中式 NodeManager 时可以同时关闭它们：

UDP 服务发现默认关闭。默认创建 `Rpc` 时不会绑定 UDP 端口、发送节点广播或接受远端广播；启用无主服务发现时必须显式配置 `listen` 或 `broadcast`。组件不强制 UDP 配置 `apiKey`，调用方可以在可信网络中自行决定是否启用签名；但未配置 `apiKey` 时，UDP 节点上线、心跳和下线数据不会验证来源，能够访问该 UDP 端口的程序都可能伪造服务节点。生产环境是否允许无签名发现及其网络隔离由调用方负责。

```php
$discoveryConfig = new DiscoveryConfig(
    // 关闭后不会创建 UDP Socket 或 UDP 广播定时器。
    listen: false,
    broadcast: false,
);
```

在构造 `Rpc` 时传入 UDP 服务发现配置；`attachServer()` 会自动完成端口预检和 assist-worker 挂载：

```php
use EasySwoole\Rpc\Server\DiscoveryConfig;
use EasySwoole\Rpc\RpcConfig;
use EasySwoole\Rpc\SecurityConfig;

$rpc = new Rpc(
    config: new RpcConfig(
        server: $serverConfig,
        discovery: new DiscoveryConfig(
            listen: true,             // 接收其他实例广播的节点信息
            listenAddress: '0.0.0.0', // UDP 绑定地址
            listenPort: 9701,         // attach 时先检测端口是否可用
            broadcast: true,          // 定时广播本机 Service 节点
            broadcastAddresses: [
                // 填写其他 RPC 实例实际监听的 UDP 地址。
                '127.0.0.1:9702',
                '192.168.1.20:9701',
            ],
            broadcastIntervalMs: 5000,
        ),
        security: new SecurityConfig(
            apiKey: 'replace-with-a-shared-api-key', // 通信双方必须一致
            timestampToleranceSeconds: 30,           // 拒绝超出窗口的重放包
        ),
    ),
);

$rpc->server()->attachServer($server);
$server->start();
```

当 `listen=true` 时，`attachServer()` 会先在主进程中尝试 bind UDP 地址。若端口已被其他程序占用、地址不可用或当前用户没有监听权限，会立即抛出异常，避免 assist-worker 启动后反复退出。预检成功后会立即释放探测 Socket，再由 `AssistWorker` 正式监听。

`SecurityConfig` 是 RPC 请求、RPC 响应与 UDP 服务发现共用的安全配置。所有通信方必须使用相同的 `apiKey`；请求、响应和 UDP payload 使用 HMAC-SHA256 签名，并验证时间戳是否处于允许窗口内。`Request` 和 `Response` 本身只保存业务数据，不持有时间戳或签名；`ProtocolCodec` 在 Transport 真正序列化数据时才加入时间戳并生成签名，服务端响应也遵循相同规则。签名前会递归规范化对象键顺序，避免等价 JSON 因键顺序不同产生不同签名。服务端会把请求的 `requestUUID` 原样写入响应，客户端再校验二者是否一致。签名只保证来源和内容没有被篡改，不加密 `arg`、`clientArg`、`result` 或节点信息；需要加密业务数据时，应由应用层自行约定。未配置 `apiKey` 时关闭签名验证。`broadcastAddresses` 应填写其他实例实际监听的 UDP 地址。

`Request::toJson()` 和 `Response::toJson()` 可以输出适合日志与排障的业务 JSON。例如：

```php
$requestJson = $request->toJson();
$responseJson = $response->toJson();
```

```json
{"service":"User","module":"Profile","action":"detail","arg":{"id":1001},"clientArg":{"traceId":"trace-001"},"requestUUID":"request-id"}
```

```json
{"status":0,"result":{"id":1001,"name":"张三"},"msg":null,"nodeHealthStatus":"healthy","requestUUID":"request-id"}
```

以上内容不包含 Transport 在发送时附加的 `timestamp` 和 `signature`。其中可能包含业务敏感数据，是否写入日志以及如何脱敏由调用方决定。

组件不实现协议层重放缓存。UDP 服务发现只应在调用方认可的可信内网中启用，因此不会为广播包维护 nonce 或去重状态。TCP 请求是否允许重复执行属于业务语义：支付、创建订单等写操作应由业务层使用业务单号、幂等键或持久化状态完成去重，RPC 框架不会把相同 `requestUUID` 自动判定为同一笔业务。

`ProtocolConfig::maxPackageSize` 同时提供给服务端 listener 和默认 `TcpTransport`，避免两侧包大小限制各自配置后不一致。客户端发送前会校验完整协议包大小，并通过 `Swoole\Coroutine\Socket::sendAll()` 保证请求全部写入；响应通过 `recvPacket()` 按相同长度头协议完整读取。`DiscoveryConfig` 会在构造时校验 UDP 监听端口、广播周期和所有广播地址；开启广播时必须至少提供一个有效的 `host:port`。节点 TTL 仍属于 NodeManager 自身配置，因为不同注册中心可能使用本地时间、Redis TTL 或 Etcd lease，RPC 配置不会暗中推断它。

节点过期判断属于 NodeManager 的职责。内置 `SwooleTableNodeManager` 默认只返回最近 15 秒内收到过心跳的节点，并在查询时移除过期记录；可通过 `nodeTtlSeconds` 调整：

```php
$nodeManager = new SwooleTableNodeManager(nodeTtlSeconds: 30);
```

默认 Table 的 `service` 字段为 64 字节，完整节点 JSON 字段为 4096 字节，对应公开常量 `SERVICE_COLUMN_BYTES` 和 `NODE_COLUMN_BYTES`。写入内容超过字段容量或 JSON 编码失败时，`online()`、`heartbeat()` 返回 `false`，由调用位置通过 `onRuntimeError` 记录，不会写入可能被截断的数据。读取时发现损坏记录会将其删除并跳过，不会阻断同一服务下其他健康节点。如果传入自行创建的 `Swoole\Table`，必须使用相同的字段名称、类型和容量。

`ServiceNode` 只保存客户端发现和连接所需的服务名、节点身份、节点名称、权重、对外地址与端口。`listenAddress` 只属于 `ServerConfig` 的本地监听配置，不会写入 ServiceNode、注册中心或 UDP 广播数据。

自定义 NodeManager 也必须在 `getNodes()` 中只返回当前可用节点。例如 Redis 实现可以依赖键 TTL，Etcd 实现可以依赖 lease，而不需要 `AssistWorker` 识别或清理具体存储。

`getNodes(string $serviceName): array|false` 使用明确的读取语义：空数组表示注册中心查询成功但当前没有可用节点，`false` 表示本次注册中心查询失败。查询失败会触发 `onRuntimeError`，客户端本次仍返回 `NOT_AVAILABLE_NODE`，后续请求会继续重新查询，以便网络恢复后自动恢复服务发现。

没有可用节点时请求不会进入 TCP Transport，也不会生成一个虚假的随机请求标识；`NOT_AVAILABLE_NODE` 响应的 `requestUUID` 为空字符串。

UDP 数据处理会先独立完成数据格式和签名验证；非法或不可信数据直接忽略，不进入全局错误回调。验证通过后才调用 NodeManager，远端节点心跳或下线返回 `false` 时会单独触发 `onRuntimeError`，不会再被误判成非法广播包。

纯客户端网关可以配置为只监听、不广播，用来接收服务节点：

```php
$rpc = new Rpc(config: new RpcConfig(
    discovery: new DiscoveryConfig(
        listen: true, // 网关接收服务节点广播
        listenPort: 9702,
        broadcast: false, // 网关没有 Service，无需向外广播
    ),
    security: new SecurityConfig(apiKey: 'replace-with-a-shared-api-key'),
));
$rpc->server()->attachServer($gatewayServer);
```

`AssistWorker` 继承自 `EasySwoole\Component\Process\AbstractProcess`。节点名、心跳周期、UDP 与安全设置由只读的 `AssistWorkerConfig` 统一约束；NodeManager 和 RuntimeErrorReporter 作为运行依赖单独传入，不会混入配置。进程名称会拼接 `nodeName`，例如 `rpc-assist-worker.user-rpc-1`；`nodeName` 为空时保持为 `rpc-assist-worker`。本机心跳和 UDP 广播使用不同的定时器；关闭 UDP 时不会创建 Socket 或 UDP 定时器。

没有节点广播时，UDP `recvfrom()` 会以协程方式正常等待，不会被视为错误。外部链路或网卡短暂中断通常同样不会破坏已经绑定的 UDP Socket。只有首次创建或 bind 失败时，AssistWorker 才会记录 `onRuntimeError`，并按照 `socketRetryIntervalMs`（默认 5000 毫秒）低频尝试创建 Socket；本地节点心跳不受影响。`recvfrom()` 明确返回 `false` 通常意味着进程或 Socket 正在关闭，接收循环会直接结束，不做无意义的重建；正常关停时也不会记录错误。

## 6. 服务节点生命周期

Service 只负责请求处理，不持有 ServiceNode 或 NodeManager。节点状态由 Server 协调，并最终交给 `AbstractNodeManager`：

```php
$rpc->server()->onlineService('User');
// heartbeat 只刷新节点存活时间，不会重新注册 Service 定义。
$rpc->server()->heartbeatService('User');
// offline 会从 NodeManager 中移除当前实例的 User 节点。
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
        // nodes 已由 NodeManager 过滤为当前可用节点。
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
- `online()`、`heartbeat()`、`offline()` 和 `getNodes()` 必须支持多个进程并发调用。

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

    public function offline(ServiceNode $node): bool
    {
        // 删除节点
        return true;
    }

    public function getNodes(string $serviceName): array|false
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

`Response::getStatus()` 返回 `ResponseStatus` 枚举；`Response::isSuccess()` 仅在状态为 `ResponseStatus::OK` 时返回 `true`。JSON 协议中仍使用对应的整数值。

服务端会对 Response 进行最终序列化。如果 Action 返回资源、递归对象等无法编码为 JSON 的结果，服务端会触发全局 `onException`，丢弃非法结果，并向客户端返回保留原 `requestUUID` 的 `SERVICE_ERROR`，不会把业务结果编码错误伪装成 TCP 网络失败。客户端会严格校验响应状态、节点健康状态和 `requestUUID` 的字段类型；格式不合法时统一作为 `RESPONSE_DECODE_ERROR` 处理。

### NodeHealthStatus

`Response::getNodeHealthStatus()` 返回独立的 `NodeHealthStatus`，用于向客户端提供节点健康提示：

| 节点标记 | 协议值 | 默认产生场景 | 默认处理 |
| --- | --- | --- | --- |
| `NodeHealthStatus::HEALTHY` | `healthy` | 正常响应，或业务代码已明确处理异常 | 保留节点；业务失败不会触发重试或下线 |
| `NodeHealthStatus::SUSPECT` | `suspect` | 未处理的 Service/Module 异常，以及客户端传输或响应校验错误 | 进入连续失败统计，达到阈值后下线 |
| `NodeHealthStatus::OFFLINE` | `offline` | 服务主动停止，或服务端明确要求摘除节点 | 立即下线，并在启用重试时选择其他节点 |

正常响应和业务代码明确处理过的异常默认是 `HEALTHY`；Service 或 Module 未处理的异常默认是 `SUSPECT`；服务主动停止接收请求时是 `OFFLINE`。业务层捕获异常后可以明确保留节点：

```php
use EasySwoole\Rpc\Protocol\NodeHealthStatus;
use EasySwoole\Rpc\Protocol\ResponseStatus;

return $response
    ->setStatus(ResponseStatus::SERVICE_ERROR)
    // 明确这是业务失败，节点仍健康，不累计连续失败次数。
    ->setNodeHealthStatus(NodeHealthStatus::HEALTHY)
    ->setMsg('Business operation failed.');
```

节点健康标记包含在 Response 签名中，不能在传输过程中被修改。它只是服务端提示，最终的重试和下线行为仍由 `NodeFailurePolicyInterface` 决定。

### ResponseStatus

| 状态 | 协议值 | 含义 |
| --- | ---: | --- |
| `ResponseStatus::OK` | `0` | 调用成功 |
| `ResponseStatus::NOT_AVAILABLE_NODE` | `1001` | 没有可用节点 |
| `ResponseStatus::CONNECT_TIMEOUT` | `1002` | TCP 连接阶段明确发生超时 |
| `ResponseStatus::SERVER_TIMEOUT` | `1003` | 已连接后等待服务响应明确发生超时 |
| `ResponseStatus::TRANSPORT_ERROR` | `1004` | 拒绝连接、连接重置、对端提前关闭、发送失败等非超时传输错误 |
| `ResponseStatus::RESPONSE_DECODE_ERROR` | `1005` | 客户端无法解析响应、签名验证失败或响应中的 request UUID 不匹配 |
| `ResponseStatus::PACKAGE_READ_TIMEOUT` | `2001` | 服务端读取请求超时 |
| `ResponseStatus::ILLEGAL_PACKAGE` | `2002` | 数据包、请求格式、请求签名或时间戳非法 |
| `ResponseStatus::SERVICE_SHUTDOWN` | `3000` | 服务已停止接收请求；默认同时标记节点为 `OFFLINE` |
| `ResponseStatus::SERVICE_NOT_EXIST` | `3001` | 服务不存在 |
| `ResponseStatus::MODULE_NOT_EXIST` | `3002` | Module 不存在 |
| `ResponseStatus::ACTION_NOT_EXIST` | `3003` | Action 不存在或未标记 `RpcAction` |
| `ResponseStatus::SERVICE_ERROR` | `3004` | 服务执行异常；是否影响节点由 `NodeHealthStatus` 决定 |

### NodeFailureAction

`NodeFailureAction` 不进入网络协议，它是客户端 `NodeFailurePolicyInterface` 对 Response 作出的最终决策：

| 决策 | 含义 |
| --- | --- |
| `NodeFailureAction::KEEP` | 保留当前节点，不自动重试 |
| `NodeFailureAction::RETRY_ANOTHER` | 保留节点但为本次请求重新选择节点；是否继续取决于 `retryTimes` |
| `NodeFailureAction::OFFLINE` | 从 NodeManager 中移除节点，并在允许重试时重新选择节点 |

默认 `ConsecutiveFailurePolicy` 的转换关系如下：

| 输入 | 决策 |
| --- | --- |
| `NodeHealthStatus::OFFLINE` 或 `ResponseStatus::SERVICE_SHUTDOWN` | `OFFLINE` |
| `NodeHealthStatus::SUSPECT` | 未达到阈值时 `RETRY_ANOTHER`，达到阈值时 `OFFLINE` |
| 连接、传输、响应超时或解码错误 | 未达到阈值时 `RETRY_ANOTHER`，达到阈值时 `OFFLINE` |
| `NodeHealthStatus::HEALTHY` 的业务响应 | `KEEP` |

## 10. 异常体系

组件主动抛出的配置、协议、注册、挂载与客户端状态异常都继承 `EasySwoole\Rpc\Exception\RpcException`。TCP 连接失败、超时、发送失败、连接关闭和响应解码失败属于可预期的运行结果，`TcpTransport` 会直接返回带对应 `ResponseStatus` 和 `NodeHealthStatus::SUSPECT` 的 `Response`，不会先转换成异常。只有 Transport 内真正未预期的 `Throwable` 才由 `RequestExecutor` 兜底并通知 `onRuntimeError`。业务 Service 和 Module 抛出的异常仍按其各自 `onException()` 约定处理，不会被强制包装成组件异常。

## 11. 运行测试

```bash
composer install
composer test
```

## 项目结构

```text
src/
├── AbstractInterface/  抽象服务、节点管理器和扩展接口
├── Attribute/          RpcAction 等 PHP Attribute
├── Client/             Client 编排、RequestExecutor 与 TCP Transport
├── Exception/          RPC 统一异常基类
├── Protocol/           DTO、签名编解码与长度头协议
├── Server/             门面、ServiceRegistry、请求处理、挂载及 AssistWorker
└── Utility/            Swoole Table 节点管理和默认节点选择器
```
