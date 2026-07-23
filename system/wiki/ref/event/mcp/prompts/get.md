# `mcp:prompts/get:*` Event

The MCP `prompts/get` JSON-RPC method. Dispatched by the [MCP gateway](:Zolinga Core:Running the System:MCP) as a [`Prompts\GetEvent`](:Zolinga Core:Events and Listeners:MCP) with the `mcp` origin.

The event type includes the URI scheme as a suffix: `mcp:prompts/get:<scheme>`. For example, a request with `name = "mcp-system:..."` dispatches as `mcp:prompts/get:mcp-system`. This lets handlers register for specific URI schemes.

The system-provided [`\Zolinga\System\Mcp\McpPromptsGetHandler::onGet()`](:ref:class:Zolinga\\System\\Mcp\\McpPromptsGetHandler) handles the `mcp-system` scheme. It parses the name, loads the `.meta.json` descriptor, resolves `content.uri` file references to `content.text`, applies `{{arg}}` placeholder substitution, and returns the `messages` array.

## Request

| Field             | Type   | Notes |
|-------------------|--------|-------|
| `params.name`     | `string` | Required. The prompt identifier (e.g. `mcp-system:ipdefender:trademark-search`). |
| `params.arguments`| `object`  | Optional. Key-value map of argument substitutions (e.g. `{"query": "ACME"}`). |

## Response (set on `$event->response`)

The response is a `{ description?, messages: [...] }` payload:

| Field         | Type   | Notes |
|---------------|--------|-------|
| `description` | `string` | Optional, from `.meta.json`. |
| `messages`    | `array`  | Array of `{ role, content }` objects. |

Each message has:

| Field      | Type   | Notes |
|------------|--------|-------|
| `role`     | `string` | `user` or `assistant`. Invalid roles reset to `user`. |
| `content`  | `object` | `{ type: "text", text: "..." }` (or image/audio/resource per MCP spec). |

The internal `content.uri` field (used in `.meta.json` for file references) is resolved to `content.text` and stripped from the response. Internal `module://` paths never reach the client.

## Example

```bash
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"prompts/get","params":{"name":"mcp-system:ipdefender:trademark-search","arguments":{"query":"ACME"}}}'
```

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "description": "Prompt template for trademark similarity search.",
    "messages": [
      {
        "role": "user",
        "content": {
          "type": "text",
          "text": "Search for trademarks similar to \"ACME\"."
        }
      }
    ]
  }
}
```

## Error Statuses

| Status        | When |
|---------------|------|
| `NOT_FOUND`   | Module does not exist or `.meta.json` file not found. |
| `BAD_REQUEST` | Required argument missing from `params.arguments`. |
| `ERROR`       | `messages` field missing from `.meta.json`, `content.uri` uses non-`module://` scheme, or `content.uri` resolves outside module directory. |

## Security

- Directory traversal is blocked: `basename()` is applied to both module and basename components.
- Module existence is explicitly checked against `$api->manifest->moduleNames`.
- `content.uri` must use `module://` scheme and must resolve within the module directory (`realpath()` containment check).
- Only `mcp-*` URI schemes are accepted (enforced by `validateResponse()`).

## See Also

- [MCP Prompts](:Zolinga Core:MCP:Prompts)
- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [`mcp:prompts/list` event](:ref:event:mcp/prompts/list)