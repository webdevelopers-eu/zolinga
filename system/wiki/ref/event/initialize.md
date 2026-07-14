# `initialize` Event

The MCP `initialize` JSON-RPC method. Dispatched by the [MCP gateway](:Zolinga Core:Running the System:MCP) at `public/mcp/index.php` with the [MCP event class](:Zolinga Core:Events and Listeners:MCP) and the `mcp` origin.

The system-provided [`\Zolinga\System\Mcp\McpInitializeHandler`](:ref:class:Zolinga\System\Mcp\McpInitializeHandler) handles this event and returns the lifecycle initialization payload:

- `protocolVersion` — the MCP protocol version this server implements.
- `capabilities` — server capabilities (currently `{ tools: { listChanged: false } }`).
- `serverInfo` — `{ name, title, version }` from the system manifest.
- `instructions` — a human-readable description of the server.

Per the [MCP specification](https://modelcontextprotocol.io/specification/2025-06-18/basic/lifecycle), the `initialize` response does **not** list tools. Use `tools/list` for tool discovery.

Module authors normally do not implement their own handler for `initialize`.

## Request

| Field        | Type   | Notes |
|--------------|--------|-------|
| `params.protocolVersion` | `string` | The protocol version the client supports (e.g. `2025-06-18`). Echoed back unchanged. |
| `params.capabilities`    | `object` | The client's capability flags. |
| `params.clientInfo`      | `object` | `{ name, version, title? }` identifying the client. |

The handler does not validate or use the `params` payload — it always returns the current server capabilities — but the `params` are still available on `$event->request` for custom handlers.

## Response (set on `$event->response`)

| Field                 | Type   | Notes |
|-----------------------|--------|-------|
| `protocolVersion`     | `string` | MCP protocol version this server implements. |
| `capabilities`        | `object` | `{ tools: { listChanged: false } }` for now. |
| `serverInfo`          | `object` | `{ name, title, version }` from the system manifest. |
| `instructions`        | `string` | Human-readable description of the server. |

## Examples

Request:

```bash
curl -X POST http://localhost:8080/mcp \
  -H 'Content-Type: application/json' \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2025-06-18",
      "capabilities": {},
      "clientInfo": { "name": "my-client", "version": "1.0.0" }
    }
  }'
```

Response:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "protocolVersion": "2025-06-18",
    "capabilities": { "tools": { "listChanged": false } },
    "serverInfo": { "name": "Zolinga", "title": "Zolinga MCP Gateway", "version": "1.5.0" },
    "instructions": "This Zolinga server exposes its event-driven API as MCP tools..."
  }
}
```

## See Also

- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [MCP Events](:Zolinga Core:Events and Listeners:MCP)
- [`tools/list` event](:ref:event:tools/list)
- [`tools/call` event](:ref:event:tools/call)
