# FileBuddy PHP 8.1 控制面

这里提供首版控制面：官网首页、账号注册/登录、Bearer 会话、Workspace 持久化、MCP/API 连接地址与 Key 生成，以及健康检查和传输阈值配置。文件读写桥接、设备心跳、Relay 信令、用量记账和 OSS 临时凭证仍需在后续阶段接入。

## 本地运行

要求 PHP 8.1+：

```bash
cp .env.example .env
php -S 127.0.0.1:8080 -t public public/index.php
curl http://127.0.0.1:8080/health
```

首次使用客户端时，将服务端地址填写为 `PUBLIC_BASE_URL`，注册账号后才能创建项目并生成连接信息。API Key 只在生成响应中明文返回，服务端仅保存哈希；遗失时重新生成即可。

数据库和 OSS 凭证只从服务器环境变量读取，不得提交到 Git。发版接入 OSS 前需要确认实际 OSS endpoint、bucket、区域及最小权限 RAM 用户。
