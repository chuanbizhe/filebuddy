# PHP 8.1 部署准备

## 原则

服务器部署只上传 `server/public` 与服务端依赖，不上传 `.env`、数据库密码、FTP 密码、OSS 密钥或本地开发产物。生产环境通过 Web Server 将 PHP 8.1-FPM 指向 `public/index.php`，并限制 `server/data` 目录不可被 Web 直接访问。

## 上线前配置

在服务器环境变量或受保护配置中设置：

```text
APP_ENV=production
APP_KEY=<随机长密钥>
DB_PATH=<服务器私有路径>/filebuddy.sqlite
RELAY_THRESHOLD_BYTES=5242880
OSS_ENDPOINT=<待确认>
OSS_BUCKET=<待确认>
OSS_ACCESS_KEY_ID=<待确认>
OSS_ACCESS_KEY_SECRET=<待确认>
```

OSS 配置需要在正式发版前确认 endpoint、bucket、地域、临时凭证策略和 Lifecycle 规则。当前代码不会主动连接 OSS，也不会把任何凭据写入仓库。

## 低频 FTP 发布建议

FTP 发布时使用人工确认后的目标目录，单次上传一个文件并在文件之间保留间隔；先上传到临时文件名，校验大小后再由服务器侧切换为正式文件名。不要循环扫描或高频重试，遇到 `553`、超时或权限错误应停止并人工检查，不宣称部署完成。
