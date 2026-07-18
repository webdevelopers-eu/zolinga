# MCP Events

`\Zolinga\System\Events\McpEvent` is the event class fired by the [MCP gateway](:Zolinga Core:Running the System:MCP) at `public/mcp/index.php`. The class extends [`RequestResponseEvent`](:Zolinga Core:Events and Listeners) and carries the JSON-RPC request id alongside the standard request/response pair.

The gateway dispatches `McpEvent` for every JSON-RPC method. For `tools/call` the event type is the bare tool name (`params.name`) and the gateway wraps the handler's response in the MCP `{ content, isError, structuredContent }` envelope. For all other methods the gateway serializes `$event->response` verbatim as the JSON-RPC `result`. MCP tools and other MCP events are uniform: the only distinction is that a `tools/call` invocation sets the `isToolCall` flag on the event and is wrapped in the MCP envelope.

## Origin

MCP events are always dispatched with the `mcp` [`OriginEnum`](:Zolinga Core:Events and Listeners) value. To opt in to MCP delivery, a listener must include `"mcp"` in its `origin` array (or the wildcard `"*"`).

## `McpEvent`

### Construction

```php
$event = new McpEvent(
    type: 'mcp:tools/list',
    jsonrpcId: 1,
    request: [],
    response: ['tools' => [...]]
);
```

| Property     | Type                              | Notes |
|--------------|-----------------------------------|-------|
| `type`       | `string`                          | The JSON-RPC `method` prefixed with `mcp:` (e.g. `tools/list` → `mcp:tools/list`; `notifications/initialized` → `mcp:notifications/initialized`). For `tools/call` it is the bare tool name (`params.name`). |
| `jsonrpcId`  | `string\|int\|null`               | The JSON-RPC `id`. `null` indicates a notification. |
| `isToolCall` | `bool`                            | `true` when the JSON-RPC `method` is `tools/call`. The gateway uses this to decide envelope wrapping and `isError` mapping. |
| `request`    | `ArrayAccess\|array`              | The JSON-RPC `params` payload. For `tools/call` it is `params.arguments`. |
| `response`   | `ArrayAccess\|array`              | Populate this with whatever the JSON-RPC `result` should be. For plain events the gateway serializes it under `result` as-is. For `tools/call` it becomes `result.structuredContent`. |

The event is `StoppableInterface` — calling `$event->stopPropagation()` skips remaining listeners, just like for other Zolinga events.

### Status → JSON-RPC Error Code (non-`tools/call`)

For plain `McpEvent` (non-`tools/call`), the gateway maps `$event->status` to a JSON-RPC `error.code`:

| Status                     | JSON-RPC code | Meaning |
|----------------------------|---------------|---------|
| `OK`, 2xx, 3xx             | —             | Result is returned. |
| Undetermined (no listener) | -32601        | Method not found. |
| `BAD_REQUEST`              | -32602        | Invalid params. |
| `NOT_FOUND`, `NOT_IMPLEMENTED` | -32601    | Method not found. |
| `UNAUTHORIZED`, `FORBIDDEN`| -32600        | Invalid request. |
| Anything >= 500            | -32603        | Internal error. |

## `tools/call` events

For `tools/call` invocations the gateway dispatches `McpEvent` with `type = "<name>"` (where `<name>` is the JSON-RPC `params.name` argument) and `isToolCall = true`. The gateway always wraps the handler's response in the MCP `{ content, isError, structuredContent }` envelope, never in a JSON-RPC `error` block.

### Tool name validation

`<name>` must match `[A-Za-z0-9_-]{1,64}` (enforced by `McpHelper::isValidToolName()`). The same rule applies in two places so the manifest and the wire contract stay in sync:

- A `tools/call` request with a `name` that fails the check is rejected with a JSON-RPC `-32602 Invalid params` error before the event is dispatched.
- A listener whose declared `event` name (used as the tool name) is non-conforming is skipped by `tools/list` (and logged via `$api->log->error('system:mcp', ...)`) so it is neither advertised nor callable.

Pick a name from `[A-Za-z0-9_-]{1,64}` (the convention used by Claude Desktop, Cursor, and other major MCP clients) and stick to it.

### What handlers do and don't set

- **Set `$event->response`** to the **raw structured payload** (the object that conforms to the tool's `outputSchema`). The gateway copies it verbatim into `result.structuredContent`.
- **Call `$event->setStatus(...)`** to signal success or failure. Non-OK status (or `UNDETERMINED` when no listener handled the event) becomes `result.isError = true`; the message ends up in `result.content[0].text`.
- **Do NOT** build the `{ content, isError, structuredContent }` envelope yourself — the gateway does that. The gateway auto-generates `content` from `json_encode($response)` (or `$event->message` on error).

### Status handling

| Status set by handler | Envelope result |
|-----------------------|-----------------|
| `OK` (or 2xx/3xx)     | `isError: false`, `structuredContent` populated, `content[0].text` = JSON of `response` |
| `BAD_REQUEST` (400) or any 4xx/5xx | `isError: true`, `content[0].text` = `event->message`, `structuredContent` omitted when empty |
| `UNDETERMINED` (no listener) | Gateway promotes to `NOT_FOUND` with message `"Unknown tool: <name>"`, `isError: true` |

### Example: minimal echo tool

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

### Manifest entry

```json
{
  "event": "echo",
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

The `schema.response` declaration is **required** for the tool to be exposed in `tools/list` — `McpTools::collectTools()` skips any tool without one and logs an error.

## See Also

- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [Events and Listeners](:Zolinga Core:Events and Listeners)
- [Event Authorization](:Zolinga Core:Events and Listeners:Event Authorization)
