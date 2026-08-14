<?php
declare(strict_types=1);

final class OssGateway
{
    private function env(string $name): string { $v = $_ENV[$name] ?? getenv($name); return $v === false || $v === null ? '' : trim((string)$v); }
    public function configured(): bool { return $this->env('OSS_ENDPOINT') !== '' && $this->env('OSS_BUCKET') !== '' && $this->env('OSS_ACCESS_KEY_ID') !== '' && $this->env('OSS_ACCESS_KEY_SECRET') !== ''; }
    public function upload(string $file, string $object, string $contentType = 'application/octet-stream'): array
    {
        if (!$this->configured()) throw new RuntimeException('oss_not_configured');
        $endpoint = rtrim($this->env('OSS_ENDPOINT'), '/'); $bucket = $this->env('OSS_BUCKET'); $key = ltrim($object, '/');
        if (!preg_match('/^[A-Za-z0-9._\/-]{1,512}$/', $key)) throw new InvalidArgumentException('invalid_object_key');
        $size = filesize($file); if ($size === false || $size > 512 * 1024 * 1024) throw new RuntimeException('file_too_large');
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $endpointHost = parse_url($endpoint, PHP_URL_HOST) ?: preg_replace('#^https?://#', '', $endpoint);
        $standardEndpoint = str_contains($endpointHost, 'aliyuncs.com');
        $resource = ($standardEndpoint ? '/' . $bucket . '/' : '/') . str_replace('%2F', '/', rawurlencode($key));
        $stringToSign = "PUT\n\n{$contentType}\n{$date}\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->env('OSS_ACCESS_KEY_SECRET'), true));
        // Use Alibaba's virtual-hosted style, required by current OSS endpoints.
        $endpoint = 'https://oss-cn-beijing.aliyuncs.com';
        $resource = '/' . $bucket . '/' . str_replace('%2F', '/', rawurlencode($key));
        $stringToSign = "PUT\n\n{$contentType}\n{$date}\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->env('OSS_ACCESS_KEY_SECRET'), true));
        $url = 'https://' . rawurlencode($bucket) . '.oss-cn-beijing.aliyuncs.com/' . str_replace('%2F', '/', rawurlencode($key));
        $handle = curl_init($url); $fp = fopen($file, 'rb');
        curl_setopt_array($handle, [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fp, CURLOPT_INFILESIZE => $size, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 600, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => ['Date: ' . $date, 'Content-Type: ' . $contentType, 'Authorization: OSS ' . $this->env('OSS_ACCESS_KEY_ID') . ':' . $signature]]);
        $body = curl_exec($handle); $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE); $error = curl_error($handle); curl_close($handle); fclose($fp);
        if ($body === false || $status < 200 || $status >= 300) {
            $code = is_string($body) && preg_match('/<Code>([^<]+)<\\/Code>/', $body, $m) ? $m[1] : '';
            $reason = is_string($body) && preg_match('/<Message>([^<]+)<\\/Message>/', $body, $m) ? $m[1] : '';
            throw new RuntimeException('oss_upload_failed:' . $status . ($code !== '' ? ':' . $code : '') . ($reason !== '' ? ':' . $reason : '') . ($error ? ':' . $error : ''));
        }
        return ['object' => $key, 'size' => $size, 'status' => $status];
    }
}
