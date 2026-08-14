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
