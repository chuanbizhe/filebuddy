<?php
declare(strict_types=1);

// FileBuddy PHP 8.1 control plane and public landing page.
// Credentials must be supplied by environment variables, never by source files.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && $path === '/') {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<'HTML'
<!doctype html>
<html lang="zh-CN"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="文小哥 FileBuddy，把本地文件夹直接交给 AI。">
<title>文小哥 FileBuddy · 把本地文件夹直接交给 AI</title>
<style>
:root{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;color:#242424;background:#f7f7f5;line-height:1.55}*{box-sizing:border-box}body{margin:0}a{color:inherit;text-decoration:none}.nav{height:72px;padding:0 max(24px,calc((100% - 1120px)/2));display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #ecece9}.brand{display:flex;align-items:center;gap:10px;font-weight:700}.mark{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#2563eb;color:#fff}.brand small{display:block;color:#888;font-size:10px;letter-spacing:.08em;font-weight:500}.nav-links{display:flex;gap:24px;color:#65655f;font-size:13px}.nav-cta{padding:9px 15px;border:1px solid #dcdcd7;border-radius:9px;color:#2563eb}.wrap{max-width:1120px;margin:auto;padding:0 32px}.hero{display:grid;grid-template-columns:1.08fr .92fr;gap:72px;align-items:center;padding:92px 0 82px}.eyebrow{color:#2563eb;font-size:11px;letter-spacing:.16em;font-weight:700;margin:0 0 14px}.hero h1{font-size:52px;letter-spacing:-.055em;line-height:1.12;margin:0 0 20px}.hero p{font-size:17px;color:#6b6b65;max-width:510px;margin:0 0 28px}.buttons{display:flex;gap:10px;align-items:center}.primary,.secondary{display:inline-block;border-radius:10px;padding:12px 18px;font-size:14px}.primary{background:#2563eb;color:#fff}.secondary{border:1px solid #dcdcd7;background:#fff}.product-card{background:#fff;border:1px solid #e7e7e2;border-radius:20px;padding:22px;box-shadow:0 18px 45px #1b2b4a0c}.window-bar{display:flex;gap:6px;margin-bottom:20px}.window-bar i{width:7px;height:7px;background:#ddd;border-radius:50%}.mini-title{display:flex;justify-content:space-between;align-items:start}.mini-title strong{font-size:18px}.online{color:#2d9562;font-size:12px}.online:before{content:"";display:inline-block;width:6px;height:6px;background:#35ad71;border-radius:50%;margin:0 5px 1px 0}.path{color:#888880;font-size:12px;margin-top:4px}.action{margin:24px 0 13px;padding:15px;text-align:center;border-radius:10px;background:#f1f5ff;color:#2563eb;font-size:14px}.stats{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.stat{border:1px solid #edede8;border-radius:10px;padding:12px}.stat small{display:block;color:#8c8c84;font-size:11px}.stat strong{display:block;margin-top:3px;font-size:14px}.section{padding:72px 0;border-top:1px solid #e9e9e4}.section-head{max-width:590px;margin-bottom:28px}.section h2{font-size:30px;letter-spacing:-.035em;margin:0 0 9px}.section-head p{color:#707069;margin:0}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.feature{background:#fff;border:1px solid #e7e7e2;border-radius:16px;padding:22px}.feature b{display:block;font-size:15px;margin-bottom:8px}.feature p{color:#77776f;font-size:13px;margin:0}.number{color:#2563eb;font-size:12px;font-weight:700;margin-bottom:22px}.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}.step{padding-right:15px}.step b{display:block;margin-bottom:7px}.step p{margin:0;color:#77776f;font-size:13px}.price{display:flex;align-items:center;justify-content:space-between;gap:26px;background:#fff;border:1px solid #e7e7e2;border-radius:16px;padding:26px}.price strong{font-size:28px}.price p{color:#77776f;font-size:13px;margin:5px 0 0}.footer{padding:28px 0 42px;color:#898981;font-size:12px;display:flex;justify-content:space-between;border-top:1px solid #e9e9e4}@media(max-width:780px){.nav-links{display:none}.wrap{padding:0 22px}.hero{display:block;padding:62px 0}.hero h1{font-size:40px}.product-card{margin-top:42px}.grid,.steps{grid-template-columns:1fr}.price{display:block}.price .primary{margin-top:18px}.footer{display:block}.footer span{display:block;margin-top:8px}}
</style></head><body>
<header class="nav"><a class="brand" href="/"><span class="mark">文</span><span>文小哥<small>FILEBUDDY</small></span></a><nav class="nav-links"><a href="#how">怎么工作</a><a href="#security">安全</a><a href="#pricing">价格</a><a class="nav-cta" href="https://github.com/chuanbizhe/filebuddy">查看项目</a></nav></header>
<main>
<section class="wrap hero"><div><p class="eyebrow">LOCAL WORKSPACE FOR AI</p><h1>把本地文件夹<br>直接交给 AI</h1><p>云端 Agent 可以在你的授权范围内读取、搜索和修改本地文件。不用反复上传，也不用配置公网端口。</p><div class="buttons"><a class="primary" href="https://github.com/chuanbizhe/filebuddy">获取客户端</a><a class="secondary" href="#how">了解工作方式</a></div></div><div class="product-card"><div class="window-bar"><i></i><i></i><i></i></div><div class="mini-title"><div><strong>EloOffice</strong><div class="path">D:\Projects\EloOffice</div></div><span class="online">在线</span></div><div class="action">复制给 AI</div><div class="stats"><div class="stat"><small>连接方式</small><strong>直连优先</strong></div><div class="stat"><small>访问权限</small><strong>读写 · 删除关闭</strong></div></div></div></section>
<section class="wrap section" id="how"><div class="section-head"><h2>简单到不需要学习网络知识</h2><p>FileBuddy 把连接、打洞和传输路径藏在产品内部，你只需要选择一个目录。</p></div><div class="steps"><div class="step"><div class="number">01</div><b>选择文件夹</b><p>通过系统选择器授权一个项目目录。</p></div><div class="step"><div class="number">02</div><b>创建项目</b><p>设置读写权限，删除权限默认关闭。</p></div><div class="step"><div class="number">03</div><b>复制给 AI</b><p>生成与项目权限一致的连接内容。</p></div><div class="step"><div class="number">04</div><b>开始工作</b><p>Agent 读取、搜索并安全修改本地文件。</p></div></div></section>
<section class="wrap section" id="security"><div class="section-head"><h2>文件留在你的设备上</h2><p>本地目录是明确授权的 Workspace，不是整台电脑的远程控制入口。</p></div><div class="grid"><div class="feature"><b>目录隔离</b><p>云端只看到 /workspace，真实磁盘路径不会暴露。</p></div><div class="feature"><b>版本保护</b><p>写入前检查最新版本，发现本地更新就拒绝覆盖。</p></div><div class="feature"><b>自动降级</b><p>P2P 优先，必要时中转，大文件使用临时传输并自动清理。</p></div></div></section>
<section class="wrap section" id="pricing"><div class="price"><div><strong>¥1.99 / 月</strong><p>基础连接服务 · 无限 P2P · MCP / API 接入</p></div><a class="primary" href="https://github.com/chuanbizhe/filebuddy">查看项目进度</a></div></section>
</main><footer class="wrap footer"><span>文小哥 FileBuddy</span><span>把本地文件夹直接交给 AI</span></footer>
</body></html>
HTML;
    exit;
}

$json = static function (array $payload, int $status = 200): never {
    header('Content-Type: application/json; charset=utf-8');
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
