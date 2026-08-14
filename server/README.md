# FileBuddy PHP 8.1 控制面

这里提供首版控制面骨架：健康检查、传输阈值配置和会话创建接口。生产部署前必须接入真实认证、Workspace 持久化、设备心跳、Relay 信令、用量记账和 OSS 临时凭证服务。

## 本地运行

要求 PHP 8.1+：

```bash
cp .env.example .env
php -S 127.0.0.1:8080 -t public public/index.php
curl http://127.0.0.1:8080/health
```

数据库和 OSS 凭证只从服务器环境变量读取，不得提交到 Git。发版接入 OSS 前需要确认实际 OSS endpoint、bucket、区域及最小权限 RAM 用户。
