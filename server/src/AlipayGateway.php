<?php
declare(strict_types=1);

final class AlipayGateway
{
    private string $gateway = 'https://openapi.alipay.com/gateway.do';
    private string $appId;
    private string $privateKey;
    private string $publicKey;

    public function __construct()
    {
        $this->appId = $this->env('ALIPAY_APP_ID');
        $this->privateKey = $this->env('ALIPAY_PRIVATE_KEY');
        $this->publicKey = $this->env('ALIPAY_PUBLIC_KEY');
    }

    public function configured(): bool
    {
        return $this->appId !== '' && $this->privateKey !== '' && $this->publicKey !== '';
    }

    public function precreate(string $orderNo, string $amount, string $subject, string $notifyUrl): array
    {
        return $this->request('alipay.trade.precreate', [
            'out_trade_no' => $orderNo,
            'total_amount' => number_format((float)$amount, 2, '.', ''),
            'subject' => mb_substr($subject, 0, 128),
            'timeout_express' => '2h',
        ], $notifyUrl);
    }

    public function query(string $orderNo): array
    {
        return $this->request('alipay.trade.query', ['out_trade_no' => $orderNo]);
    }

    public function verifyNotification(array $params): bool
    {
        if (!$this->configured() || empty($params['sign'])) return false;
        $sign = (string)$params['sign'];
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $content = implode('&', array_map(static fn($key, $value) => $key . '=' . $value, array_keys($params), $params));
        $key = $this->publicPem($this->publicKey);
        return openssl_verify($content, base64_decode($sign, true) ?: '', $key, OPENSSL_ALGO_SHA256) === 1;
    }

    public function appId(): string { return $this->appId; }

    private function request(string $method, array $bizContent, string $notifyUrl = ''): array
    {
        if (!$this->configured()) throw new RuntimeException('支付宝服务尚未配置');
        $params = ['app_id' => $this->appId, 'method' => $method, 'format' => 'JSON', 'charset' => 'utf-8', 'sign_type' => 'RSA2', 'timestamp' => date('Y-m-d H:i:s'), 'version' => '1.0', 'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        if ($notifyUrl !== '') $params['notify_url'] = $notifyUrl;
        ksort($params);
        $content = implode('&', array_map(static fn($key, $value) => $key . '=' . $value, array_keys($params), $params));
        $private = $this->privatePem($this->privateKey);
        if (!openssl_sign($content, $signature, $private, OPENSSL_ALGO_SHA256)) throw new RuntimeException('支付宝签名失败');
        $params['sign'] = base64_encode($signature);
        $handle = curl_init($this->gateway);
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($params), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8']]);
        $raw = curl_exec($handle); $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE); $error = curl_error($handle); curl_close($handle);
        if ($raw === false || $status !== 200) throw new RuntimeException('支付宝网关请求失败' . ($error !== '' ? '：' . $error : ''));
        $decoded = json_decode($raw, true); $node = str_replace('.', '_', $method) . '_response'; $response = is_array($decoded) ? ($decoded[$node] ?? []) : [];
        if (!is_array($response) || (string)($response['code'] ?? '') !== '10000') throw new RuntimeException((string)($response['sub_msg'] ?? $response['msg'] ?? '支付宝下单失败'));
        return $response;
    }

    private function env(string $name): string { $value = $_ENV[$name] ?? getenv($name); return $value === false || $value === null ? '' : trim((string)$value); }
    private function privatePem(string $value): string { return str_contains($value, 'BEGIN') ? $value : "-----BEGIN RSA PRIVATE KEY-----\n" . chunk_split(preg_replace('/\s+/', '', $value), 64, "\n") . "-----END RSA PRIVATE KEY-----"; }
    private function publicPem(string $value): string { return str_contains($value, 'BEGIN') ? $value : "-----BEGIN PUBLIC KEY-----\n" . chunk_split(preg_replace('/\s+/', '', $value), 64, "\n") . "-----END PUBLIC KEY-----"; }
}
