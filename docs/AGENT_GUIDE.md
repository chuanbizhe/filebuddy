# FileBuddy Agent 使用说明

> 本文档面向接入 FileBuddy 的 AI Agent。连接信息中的 API Key 由用户单独提供，本文档不包含任何密钥。

## 你可以做什么

FileBuddy 将用户授权的本地文件夹映射为虚拟目录 `/workspace`。你可以在授权范围内：

- 查看目录和文件信息
- 搜索文件名与文本内容
- 读取文件或指定范围
- 创建文件、写入文件、应用 patch
- 重命名、移动和复制文件
- 在用户允许时删除文件

## 重要规则

1. 永远只访问 `/workspace`，不要猜测或请求真实磁盘路径。
2. 修改文件前先读取当前内容，确认版本没有变化。
3. 删除权限默认关闭；没有明确权限时不得删除。
4. 大文件分段读取，不要一次性读取整个项目。
5. 写入前说明将要修改的文件和目的；遇到冲突时停止并请求用户确认。
6. 不要索要用户的 FileBuddy API Key、短信密钥、OSS 密钥或服务器凭据。

## 推荐工作流

```text
get_workspace_info
  -> list_files
  -> get_file_info / read_file_range
  -> search_text
  -> 修改前再次读取
  -> apply_patch 或 write_file
  -> 重新读取并报告结果
```

## MCP / API

用户在 FileBuddy 客户端中生成 MCP 地址、API 地址和 API Key 后，将它们配置到 Agent 的工具连接中。API 请求使用：

```http
Authorization: Bearer <登录令牌>
X-FileBuddy-Key: <Workspace API Key>
```

MCP 和 API 只代表连接控制面；真实文件仍留在用户设备的授权目录中。

## 回复用户时

简要说明：读取了哪些文件、修改了什么、是否成功验证。不要声称访问了 Workspace 之外的内容，也不要展示真实磁盘路径。

## HTTP 请求格式

请求使用 JSON，并在需要登录的接口带上：

```http
Content-Type: application/json
Authorization: Bearer <登录令牌>
```

Bridge 请求使用 `X-FileBuddy-Key: <Workspace API Key>`。常用接口：

```text
GET  /health
GET  /v1/config
POST /v1/auth/send-code       {"phone":"13800138000","purpose":"register"}
POST /v1/auth/register        {"phone":"13800138000","password":"至少8位","code":"123456"}
POST /v1/auth/login           {"phone":"13800138000","password":"至少8位"}
POST /v1/auth/login-code      {"phone":"13800138000","code":"123456"}
GET  /v1/workspaces           Authorization: Bearer <token>
POST /v1/workspaces           {"name":"项目","permission":"read_write","allowDelete":false}
POST /v1/workspaces/<id>/connections
GET  /v1/bridge/<id>          X-FileBuddy-Key: <key>
GET  /mcp/<id>                X-FileBuddy-Key: <key>
```

创建连接后，服务端返回 `mcpUrl`、`apiUrl`、`apiKey` 和 `agentPrompt`。API Key 只保存到安全的 Agent 配置，不要写入日志或回复内容。

未知路径或方法不会只返回空白 404，而会返回 JSON：`error=unsupported_request`、`code=FILEBUDDY_ROUTE_NOT_FOUND`、`hint`、`docs` 和 `examples`。遇到该错误时，先读取 `docs` 再按示例修正请求。

## 正确请求示例

```bash
BASE='http://filebuddy.elo.ink/index.php'
TOKEN=$(curl -sS -X POST "$BASE/v1/auth/login" \
  -H 'Content-Type: application/json' \
  -d '{"phone":"13800138000","password":"your-password"}' | jq -r .token)
WORKSPACE=$(curl -sS -X POST "$BASE/v1/workspaces" \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"我的项目","permission":"read_write","allowDelete":false}')
ID=$(echo "$WORKSPACE" | jq -r .id)
CONNECTION=$(curl -sS -X POST "$BASE/v1/workspaces/$ID/connections" \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{}')
KEY=$(echo "$CONNECTION" | jq -r .apiKey)
curl -sS "$BASE/v1/bridge/$ID" -H "X-FileBuddy-Key: $KEY"
```

最后一步成功响应必须包含 `workspace: "/workspace"` 和 `tools`。不要把真实磁盘路径、密码或 API Key 写入日志。
