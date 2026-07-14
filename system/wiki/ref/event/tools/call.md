# `tools/call` Event

The MCP `tools/call` JSON-RPC method. Dispatched by the [MCP gateway](:Zolinga Core:Running the System:MCP) as an [`McpEvent`](:Zolinga Core:Events and Listeners:MCP) with `type = "tools:call:<name>"` and the `mcp` origin.

The gateway translates `tools/call` `params.name` into the per-tool event `tools:call:<name>` and passes `params.arguments` as the event request. The tool's handler sets the raw structured payload on `$event->response`; the gateway wraps it in the MCP `{ content, isError, structuredContent }` envelope and serializes it as the JSON-RPC `result`.

## Request

| Field         | Type   | Notes |
|---------------|--------|-------|
| `params.name`        | `string` | Required. The tool name; the gateway appends it to `tools:call:` and dispatches that event. |
| `params.arguments`   | `object` | Optional. Tool arguments; becomes `$event->request`. |

## Response (gateway-built envelope on `result`)

| Field              | Type | Notes |
|--------------------|------|-------|
| `content`          | `list<{type, text}>` | Human-readable content blocks. The gateway auto-generates a single `text` block carrying `json_encode($event->response)` (or `$event->message` on error) so legacy clients that only read `content[0].text` still get the structured data. |
| `isError`          | `bool` | `true` when the handler's `$event->status` is non-OK (or stays at `UNDETERMINED` because no listener handled the event). |
| `structuredContent`| `object` | The handler's raw `$event->response` payload, normalized. Omitted on error when the response is an empty array. |

## Error Handling

- A handler that sets `$event->status` to a non-OK value (e.g. `BAD_REQUEST`, `NOT_FOUND`, `ERROR`) gets a `result.isError = true` response with the handler's message in `result.content[0].text`.
- A name that has no listener registered gets `result.isError = true` with text `"Unknown tool: <name>"` (the gateway promotes the event from `UNDETERMINED` to `NOT_FOUND` for you).
- The MCP `tools/call` spec says errors are surfaced in-band on the result object (as `isError`), never as JSON-RPC `error` blocks. The gateway respects that — for `tools/call` it never emits a JSON-RPC `error` block, regardless of the handler's status.

## Example

```bash
curl -X POST http://localhost:8080/mcp \
  -H 'Content-Type: application/json' \
  -d '{
    "jsonrpc":"2.0",
    "id": 1,
    "method": "tools/call",
    "params": { "name": "echo", "arguments": { "message": "Hello MCP" } }
  }'
```

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "content": [
      { "type": "text", "text": "{\"echo\":\"Hello MCP\",\"receivedAt\":\"2026-06-03T12:00:00+00:00\"}" }
    ],
    "isError": false,
    "structuredContent": {
      "echo": "Hello MCP",
      "receivedAt": "2026-06-03T12:00:00+00:00"
    }
  }
}
```

## Handler Example

```php
use Zolinga\System\Events\{ListenerInterface, McpEvent};
use Zolinga\System\Types\StatusEnum;

class MyEchoHandler implements ListenerInterface
{
    public function onEcho(McpEvent $event): void
    {
        $message = $event->request['message'] ?? '';
        if (!is_string($message) || $message === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing or empty "message" argument.');
            return;
        }

        // Raw structured payload (must conform to your outputSchema).
        $event->response = [
            'echo' => $message,
            'receivedAt' => date('c'),
        ];
        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

Manifest entry:

```json
{
  "event": "tools:call:echo",
  "class": "\\MyModule\\MyEchoHandler",
  "method": "onEcho",
  "origin": ["mcp"],
  "description": "Echoes the message argument back with a server timestamp.",
  "schema": {
    "request":  "module://my-module/schema/mcp/echo-request.json",
    "response": "module://my-module/schema/mcp/echo-response.json"
  }
}
```

`schema.response` is **required** for `tools/list` to expose the tool.

## See Also

- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [MCP Events](:Zolinga Core:Events and Listeners:MCP)
- [`initialize` event](:ref:event:initialize)
- [`tools/list` event](:ref:event:tools/list)
