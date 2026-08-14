<?php
declare(strict_types=1);

// FileBuddy PHP 8.1 control-plane skeleton. Credentials must be supplied by environment variables.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$json = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if ($method === 'GET' && $path === '/health') {
    $json(['ok' => true, 'service' => 'filebuddy-api', 'php' => PHP_VERSION, 'time' => gmdate('c')]);
}
if ($method === 'GET' && $path === '/v1/config') {
    $json(['relayThresholdBytes' => (int)($_ENV['RELAY_THRESHOLD_BYTES'] ?? getenv('RELAY_THRESHOLD_BYTES') ?: 5242880), 'protocolVersion' => 1]);
}
if ($method === 'POST' && $path === '/v1/sessions') {
    $body = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($body) || empty($body['workspaceId'])) $json(['error' => 'workspaceId is required'], 422);
    $json(['sessionId' => 'sess_' . bin2hex(random_bytes(10)), 'expiresIn' => 900, 'status' => 'created']);
}
$json(['error' => 'not_found'], 404);
