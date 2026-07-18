# `tools/list` Event

The MCP `tools/list` JSON-RPC method. Dispatched by the [MCP gateway](:Zolinga Core:Running the System:MCP) with the [MCP event class](:Zolinga Core:Events and Listeners:MCP) and the `mcp` origin.

The system-provided [`\Zolinga\System\Mcp\McpTools::onList()`](:ref:class:Zolinga\\System\\Mcp\\McpTools) handles this event, walks the merged manifest and returns every listener that opts in to the `mcp` origin AND declares a `schema.response` (and is not a reserved MCP protocol event) as an MCP tool. The listener's event name is used verbatim as the JSON-RPC tool `name`.

Reserved MCP protocol methods (`mcp:initialize`, `mcp:tools/list`, `mcp:notifications/*`) are excluded from the tool list. Listeners without a `schema.response` declaration are also excluded and an error is logged.

## Request

`params` is optional. The MCP spec defines an optional `cursor` for pagination, but this non-streaming gateway does not paginate and ignores it.

## Response (set on `$event->response`)

The response is a `{ tools: [...] }` payload, where each entry has at least:

| Field         | Type   | Notes |
|---------------|--------|-------|
| `name`        | `string` | The tool name (the listener's event name used verbatim); passed as `params.name` to `tools/call`. |
| `description` | `string` | From the listener's `description` in `zolinga.json`. |
| `inputSchema` | `object` | The JSON Schema from `schema.request` (or `{ type: "object", additionalProperties: true }`). |
| `outputSchema`| `object` | The JSON Schema from `schema.response`. **Required** — tools without one are skipped. |

## Example

```bash
curl -X POST http://localhost:8080/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "tools": [
      {
        "name": "echo",
        "description": "Echoes the message argument back with a server timestamp.",
        "inputSchema":  { "type": "object", "properties": { "message": { "type": "string" } }, "required": ["message"] },
        "outputSchema": { "type": "object", "properties": { "echo": { "type": "string" }, "receivedAt": { "type": "string" } }, "required": ["echo", "receivedAt"] }
      }
    ]
  }
}
```

## See Also

- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [`initialize` event](:ref:event:initialize)
- [`tools/call` event](:ref:event:tools/call)
