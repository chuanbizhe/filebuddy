<?php
declare(strict_types=1);

final class TencentSmsService
{
    private string $sdkAppId;
    private string $appKey;
    private string $sign;
    private string $templateId;

    public function __construct()
    {
        $this->sdkAppId = $this->env('TENCENT_SMS_SDK_APP_ID');
        $this->appKey = $this->env('TENCENT_SMS_APP_KEY');
        $this->sign = $this->env('TENCENT_SMS_SIGN');
        $this->templateId = $this->env('TENCENT_SMS_TEMPLATE_ID');
    }

    public function configured(): bool
    {
        return $this->sdkAppId !== '' && $this->appKey !== '' && $this->sign !== '' && $this->templateId !== '';
    }

    public function send(string $phone, string $code): array
    {
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) return ['success' => false, 'message' => '手机号格式不正确'];
        if (!$this->configured()) return ['success' => false, 'message' => '短信服务尚未配置'];
        $random = (string)random_int(1000000000, 9999999999);
        $time = time();
        $signature = hash('sha256', "appkey={$this->appKey}&random={$random}&time={$time}&mobile={$phone}");
        $payload = json_encode(['ext' => '', 'extend' => '', 'params' => [$code], 'sig' => $signature, 'sign' => $this->sign, 'tel' => ['mobile' => $phone, 'nationcode' => '86'], 'time' => $time, 'tpl_id' => (int)$this->templateId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $handle = curl_init('https://yun.tim.qq.com/v5/tlssmssvr/sendsms?sdkappid=' . rawurlencode($this->sdkAppId) . '&random=' . $random);
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
        $response = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if ($response === false) return ['success' => false, 'message' => '短信服务连接失败：' . $error];
        $result = json_decode($response, true);
        if ($status >= 200 && $status < 300 && is_array($result) && (int)($result['result'] ?? -1) === 0) return ['success' => true, 'message' => '验证码已发送'];
        return ['success' => false, 'message' => is_array($result) ? (string)($result['errmsg'] ?? '短信发送失败') : '短信服务响应无效'];
    }

    private function env(string $name): string
    {
        $value = $_ENV[$name] ?? getenv($name);
        return $value === false || $value === null ? '' : trim((string)$value);
    }
}
