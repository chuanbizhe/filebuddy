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
