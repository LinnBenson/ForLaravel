# PluginManage 接口说明

插件市场默认接口前缀为：

```text
/plugin/manage
```

所有接口均使用 Bearer Token 鉴权：

```http
Authorization: Bearer your-token
Accept: application/json
```

上传接口使用插件配置中的 `upload.token`，列表和下载接口使用 `download.token`。

## 上传插件

```http
POST /plugin/manage/upload
Content-Type: multipart/form-data
```

### 请求参数

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `rid` | string | 是 | 插件唯一标识，只允许数字、字母、`-` 和 `_` |
| `name` | string | 是 | 插件名称，最大 255 个字符 |
| `description` | string | 否 | 插件描述 |
| `version` | string | 是 | 插件版本号，最大 64 个字符 |
| `author` | string | 否 | 插件作者，最大 255 个字符 |
| `upload` | file | 是 | ZIP 压缩包，最大 20 MB |

相同 `rid` 已存在时，新版本必须高于当前版本。上传成功后会覆盖原 ZIP，并更新插件名称、描述、版本和作者，下载次数保持不变。

### 请求示例

```bash
curl -X POST 'http://127.0.0.1/plugin/manage/upload' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer your-upload-token' \
  -F 'rid=ExamplePlugin' \
  -F 'name=示例插件' \
  -F 'description=插件描述' \
  -F 'version=1.0.0' \
  -F 'author=Todu.io' \
  -F 'upload=@/path/ExamplePlugin.zip'
```

### 成功响应

```json
{
    "status": "success",
    "code": 200,
    "time": 1730000000,
    "data": "Plugin uploaded successfully."
}
```

更新已有插件时，`data` 为 `Plugin updated successfully.`。

## 获取插件列表

```http
GET /plugin/manage/list
```

列表按更新时间倒序排列；更新时间相同时按 ID 倒序排列。每页固定返回 20 条记录。

### Query 参数

| 参数 | 类型 | 必填 | 默认值 | 说明 |
| --- | --- | --- | --- | --- |
| `page` | integer | 否 | `1` | 当前页码，最小为 1 |
| `keyword` | string | 否 | 空 | 模糊搜索 `rid`、`name` 和 `description` |

### 请求示例

```bash
curl 'http://127.0.0.1/plugin/manage/list?page=1&keyword=Example' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer your-download-token'
```

### 成功响应

```json
{
    "status": "success",
    "code": 200,
    "time": 1730000000,
    "data": {
        "total": 1,
        "page": 1,
        "quantity": 1,
        "data": [
            {
                "id": 1,
                "rid": "ExamplePlugin",
                "name": "示例插件",
                "description": "插件描述",
                "version": "1.0.0",
                "author": "Todu.io",
                "count": 0,
                "created_at": "2026-08-29T00:00:00.000000Z",
                "updated_at": "2026-08-29T00:00:00.000000Z"
            }
        ]
    }
}
```

返回字段说明：

- `total`：符合条件的插件总数。
- `page`：当前页码。
- `quantity`：总页数。
- `data`：当前页的插件列表。

## 下载插件

```http
GET /plugin/manage/download/{name}
```

`name` 为插件的 `rid`。接口成功时返回 ZIP 文件流，并将该插件的下载次数加一。

### 请求示例

```bash
curl -L 'http://127.0.0.1/plugin/manage/download/ExamplePlugin' \
  -H 'Authorization: Bearer your-download-token' \
  --output ExamplePlugin.zip
```

### 成功响应

```http
HTTP/1.1 200 OK
Content-Type: application/zip
Content-Disposition: attachment; filename=ExamplePlugin.zip
```

## 错误响应

接口错误统一使用 `echoJson()` 返回：

```json
{
    "status": "error",
    "code": 404,
    "time": 1730000000,
    "data": "Plugin not found."
}
```

常见 HTTP 状态码：

| 状态码 | 说明 |
| --- | --- |
| `403` | Bearer Token 缺失或错误 |
| `404` | 插件记录或压缩包不存在 |
| `409` | 插件版本不高于当前版本，或压缩包状态冲突 |
| `422` | 请求参数验证失败 |
| `500` | 文件或数据库操作失败 |
