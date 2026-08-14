<?php
declare(strict_types=1);

// 腾讯云短信配置参考已迁移到服务端实现。
// 请通过环境变量提供：TENCENT_SMS_SDK_APP_ID、TENCENT_SMS_APP_KEY、
// TENCENT_SMS_SIGN、TENCENT_SMS_TEMPLATE_ID，禁止把真实密钥写入仓库。
require_once dirname(__DIR__) . '/server/src/TencentSmsService.php';
