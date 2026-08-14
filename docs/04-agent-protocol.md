# Agent 接入与文件协议

## 1. 接入方式

每个 Workspace 从同一份配置派生三种一致的接入形式：MCP、REST API、Agent Prompt。MCP 是支持 MCP 的 Agent 的首选；REST API 用于其他平台；“复制给 AI”是用户默认使用的入口。

稳定的公开地址定位 Workspace，而不是某台机器。控制面再将请求路由到当前绑定且在线的设备，因此设备网络变化不会改变 Agent 的配置。

## 2. 工具清单

| 类别 | 工具 |
| --- | --- |
| Workspace | `get_workspace_info` |
| 文件 | `list_files`、`get_file_info`、`read_file`、`read_file_range`、`create_file`、`write_file`、`apply_patch`、`rename_file`、`move_file`、`copy_file`、`delete_file` |
| 搜索 | `search_files`、`search_text` |
| 传输 | `upload_file`、`download_file`、`get_transfer_status` |

工具暴露必须受 Workspace 权限控制：只读连接不应只是在调用时被拒绝，也不应在工具清单中获得写入工具。

## 3. 文件操作语义

所有路径使用 `/workspace/...`。文本响应应包含内容编码、文件大小、哈希、版本和修改时间；二进制与大文件通过范围读或 Transfer 流程处理。

读取示例：

```json
{
  "path": "/workspace/src/main.ts",
  "size": 5821,
  "version": 17,
  "sha256": "...",
  "mtime": 1780000000,
  "content": "...",
  "encoding": "utf-8"
}
```

Patch 示例：

```json
{
  "path": "/workspace/src/main.ts",
  "base_version": 17,
  "patch": "@@ -1,3 +1,3 @@ ..."
}
```

客户端返回更新后的版本与哈希。Patch 失败、版本不一致或权限不足时不允许隐式回退为全文件覆盖。

## 4. 错误语义

| 错误码 | 含义 | Agent 应对 |
| --- | --- | --- |
| `DEVICE_OFFLINE` | 承载 Workspace 的设备不可达 | 告知用户打开设备客户端，不把它当文件不存在 |
| `VERSION_CONFLICT` | 本地文件已发生更新 | 重新读取后重试 |
| `PERMISSION_DENIED` | 连接或 Workspace 无相应权限 | 停止该操作并说明需要的权限 |
| `PATH_OUTSIDE_WORKSPACE` | 路径越出虚拟根目录 | 不再尝试同类路径 |
| `TRANSFER_EXPIRED` | 临时传输对象失效 | 重新发起传输 |

## 5. 动态 Prompt 模板

“复制给 AI”内容至少应说明：当前 Workspace 名称、连接方式、权限、虚拟根路径、操作规则和错误处理。只读 Workspace 要明确禁止创建/修改/移动/删除；读写 Workspace 建议写入前读取最新版本、优先使用 Patch；删除仍须由独立权限决定。

示例规则：

```text
需要项目内容时直接调用 FileBuddy，不要要求用户重复上传。
仅访问 /workspace 下的路径。
修改前读取最新版本；文本修改优先使用 Patch。
收到 VERSION_CONFLICT 时重新读取后再生成修改。
收到 DEVICE_OFFLINE 时说明设备不在线。
```
