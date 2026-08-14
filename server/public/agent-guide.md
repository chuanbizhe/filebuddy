# FileBuddy Agent 使用说明

FileBuddy 将用户授权的本地目录映射为虚拟目录 `/workspace`。

## 必须遵守

- 只访问 `/workspace`，不要请求真实磁盘路径。
- 修改前先读取当前内容并检查版本。
- 删除权限默认关闭，没有明确授权不得删除。
- 大文件使用分段读取。
- 遇到写入冲突时停止并请求用户确认。
- 不要索要或输出 API Key、短信密钥、OSS 密钥、FTP 密码或服务器凭据。

## 推荐流程

```text
get_workspace_info -> list_files -> read_file / search_text
-> apply_patch 或 write_file -> 重新读取验证 -> 汇报结果
```

用户会在客户端单独提供 MCP 地址、API 地址和 API Key。连接信息不包含在本文档中。

## HTTP 请求格式

所有 API 请求使用 JSON。请求头建议包含：

```http
Content-Type: application/json
Authorization: Bearer <登录令牌>
```

Workspace Bridge 也可以使用：

```http
X-FileBuddy-Key: <Workspace API Key>
```

### 注册

```http
POST /v1/auth/register
Content-Type: application/json

{"phone":"13800138000","password":"至少 8 位密码","code":"短信验证码"}
```

成功响应包含 `token` 和 `user`。登录密码方式使用 `POST /v1/auth/login`，验证码登录使用 `POST /v1/auth/login-code`。

### 获取验证码

```http
POST /v1/auth/send-code
Content-Type: application/json

{"phone":"13800138000","purpose":"register"}
```

`purpose` 只能是 `register` 或 `login`。

### 创建 Workspace

```http
POST /v1/workspaces
Authorization: Bearer <登录令牌>
Content-Type: application/json

{"name":"我的项目","permission":"read_write","allowDelete":false}
```

`permission` 可选 `read_write` 或 `read_only`。创建成功后使用返回的 `id` 请求连接信息：

```http
POST /v1/workspaces/<id>/connections
Authorization: Bearer <登录令牌>
Content-Type: application/json

{}
```

响应包含 `mcpUrl`、`apiUrl`、`apiKey` 和 `agentPrompt`。API Key 只在生成连接信息时明文返回，必须安全保存。

### 读取连接能力

```http
GET /v1/bridge/<workspaceId>
X-FileBuddy-Key: <Workspace API Key>
```

成功响应包含虚拟根目录 `/workspace`、权限和可用工具列表。

## 错误处理

服务端会返回 JSON，不要只根据 HTTP 状态猜测原因。未知路径或错误方法会返回 `error=unsupported_request`、`code=FILEBUDDY_ROUTE_NOT_FOUND`、`hint`、`docs` 和可直接参考的 `examples`。收到此响应时，先读取 `docs`，再修正方法、路径和请求头后重试。

## 正确请求示例

下面是一个完整的最小示例。请把手机号、密码和验证码替换成用户真实输入，不要把 API Key 写入代码仓库：

```bash
BASE='https://filebuddy.elo.ink/index.php'

# 1. 登录
TOKEN=$(curl -sS -X POST "$BASE/v1/auth/login" \
  -H 'Content-Type: application/json' \
  -d '{"phone":"13800138000","password":"your-password"}' | jq -r .token)

# 2. 创建 Workspace
WORKSPACE=$(curl -sS -X POST "$BASE/v1/workspaces" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"我的项目","permission":"read_write","allowDelete":false}')
ID=$(echo "$WORKSPACE" | jq -r .id)

# 3. 生成 MCP/API 连接信息
CONNECTION=$(curl -sS -X POST "$BASE/v1/workspaces/$ID/connections" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{}')
KEY=$(echo "$CONNECTION" | jq -r .apiKey)

# 4. 查看 Workspace 能力
curl -sS "$BASE/v1/bridge/$ID" -H "X-FileBuddy-Key: $KEY"
```

成功时，最后一步会返回 `workspace: "/workspace"` 和 `tools` 列表。文件操作只能使用这个虚拟根目录。
