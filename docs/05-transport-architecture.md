# 连接与传输架构

## 1. 总体原则

传输采用自动降级，优先级为：**P2P Direct → Relay Stream → Temporary OSS**。用户只感知连接结果，不承担网络选择。

```text
Cloud AI / Agent
      │ MCP / REST
      ▼
FileBuddy Gateway（认证、发现、会话、路由）
      │
 ┌────┴─────┐
 ▼          ▼
P2P       Relay
 │          │
 └────┬─────┘
      ▼
Local Client → Workspace

大文件：Local Client / Cloud Agent ↔ Temporary OSS
```

## 2. P2P：默认数据通道

控制面负责身份认证、设备发现、会话建立、信令与 ICE 协商；文件数据通过加密 P2P 连接直达云端 Agent 与本地客户端，不经 FileBuddy 文件服务器。可评估 WebRTC DataChannel 或 QUIC 方案，具体选型以跨平台成熟度、网络兼容性和审计能力为准。

P2P 适合绝大多数工具调用、文本读取、Patch、目录搜索和小型内容。产品承诺的“无限 P2P”以此通道的实际传输为依据。

## 3. Relay：小数据兜底

P2P 协商或连接失败时，使用流式中转：`Agent → Relay → Local Client`。Relay 尽量不落盘，仅转发加密会话内的数据，适合 JSON、Tool Call、文本、Patch、Diff、搜索结果、文件片段和小文件。

Relay 阈值初始可在约 5 MB 进行验证，但必须由服务端配置下发，不能硬编码在客户端。中转流量需被精确记账。

## 4. OSS：大文件临时中转

当文件超过 Relay 阈值或连接策略要求时，服务端创建 Transfer 并签发短期 STS / Signed URL。上传方与下载方直接连接 OSS，业务服务器不转发文件字节。

```text
上传方 → 请求 Transfer 凭证 → 控制面
上传方 ───────────直接上传──────────→ OSS
下载方 ←──────────短期下载地址───────── 控制面
下载方 ←──────────直接下载────────── OSS
```

下载完成后验证 SHA-256；确认完成即删除对象。OSS Lifecycle 作为兜底，处理崩溃、断网和超时遗留对象。

## 5. Transfer 状态机

```text
CREATED → UPLOADING → AVAILABLE → DOWNLOADING → VERIFYING → COMPLETED → DELETED
                         ↘ FAILED / EXPIRED / CANCELLED ↗
```

任意终态均进入清理流程。记录对象大小、哈希、过期时间、计费归属和删除结果，不把临时对象视为用户云盘文件。

## 6. 可观测性与降级

控制面需要记录连接尝试、P2P 成功率、Relay 原因、Transfer 生命周期、字节数与错误分类。客户端向用户展示“直连”“通过中转”“设备离线”；内部日志保留更细的网络诊断信息。任何降级都不得绕过权限、会话或加密验证。
