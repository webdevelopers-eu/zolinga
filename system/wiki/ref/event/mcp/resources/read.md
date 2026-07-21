# `mcp:resources/read:*` Event

The MCP `resources/read` JSON-RPC method. Dispatched by the [MCP gateway](:Zolinga Core:Running the System:MCP) as a [`Resources\ReadEvent`](:Zolinga Core:Events and Listeners:MCP) with the `mcp` origin.

The event type includes the URI scheme as a suffix: `mcp:resources/read:<scheme>`. For example, a request with `uri = "mcp-system:static:..."` dispatches as `mcp:resources/read:mcp-system`. This lets handlers register for specific URI schemes.

The system-provided [`\Zolinga\System\Mcp\McpResourcesReadHandler::onRead()`](:ref:class:Zolinga\\System\\Mcp\\McpResourcesReadHandler) handles the `mcp-system` scheme. It parses the URI, resolves the `.meta.json` descriptor, reads the content file, and returns it as `text` or `blob` based on the MIME type.

## Request

| Field         | Type   | Notes |
|---------------|--------|-------|
| `params.uri`  | `string` | Required. The resource URI (e.g. `mcp-system:static:ipdefender:about.md`). |

## Response (set on `$event->response`)

The response is a `{ contents: [...] }` payload. Each entry has:

| Field      | Type   | Notes |
|------------|--------|-------|
| `uri`      | `string` | The original request URI. |
| `mimeType` | `string` | From the `.meta.json` descriptor. |
| `text`     | `string` | Present for `text/*` MIME types. |
| `blob`     | `string` | Present for non-text MIME types (base64-encoded). |

## Example

```bash
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"resources/read","params":{"uri":"mcp-system:static:ipdefender:about.md"}}'
```

Text resource response:

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "contents": [
      {
        "uri": "mcp-system:static:ipdefender:about.md",
        "mimeType": "text/markdown",
        "text": "# About IP Defender\n\n..."
      }
    ]
  }
}
```

Binary resource response:

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "contents": [
      {
        "uri": "mcp-system:static:my-module:logo.png",
        "mimeType": "image/png",
        "blob": "<base64-encoded-content>"
      }
    ]
  }
}
```

## Security

- Directory traversal is blocked: `basename()` is applied to both module and basename components.
- Only `mcp-system`, `http`, and `https` URI schemes are accepted in responses (enforced by `validateResponse()`).

## See Also

- [MCP Resources](:Zolinga Core:MCP:Resources)
- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [`mcp:resources/list` event](:ref:event:mcp/resources/list)
