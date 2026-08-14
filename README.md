# 文小哥 FileBuddy

> 把本地文件夹直接交给 AI。

文小哥 FileBuddy 是面向云端 AI / Agent 的本地 Workspace 网关：在用户明确授权下，让 Agent 读取、搜索和修改一个本地目录，而不需要反复上传、下载或配置公网网络。

本仓库当前处于产品与架构设计阶段，尚未包含客户端或服务端实现。

## 文档导航

- [产品 PRD](docs/01-product-prd.md)
- [体验与 UI 规范](docs/02-user-experience-ui.md)
- [Workspace 与安全模型](docs/03-workspace-security.md)
- [Agent 接入与文件协议](docs/04-agent-protocol.md)
- [连接与传输架构](docs/05-transport-architecture.md)
- [多端客户端设计](docs/06-platform-clients.md)
- [服务端、计费与价值报告](docs/07-backend-billing.md)
- [开发路线与验收标准](docs/08-roadmap-acceptance.md)

## 产品边界

FileBuddy v1.0 不是云盘、文件同步软件、远程桌面或 AI 聊天产品；它只解决一件事：将一个用户授权的本地目录，以安全、稳定且可审计的方式交给云端 Agent 使用。
