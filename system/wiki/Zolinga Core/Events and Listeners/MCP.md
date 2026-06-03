# MCP Events

`\Zolinga\System\Events\McpEvent` is the base event class fired by the [MCP gateway](:Zolinga Core:Running the System:MCP) at `public/mcp/index.php`. The class extends [`RequestResponseEvent`](:Zolinga Core:Events and Listeners) and carries the JSON-RPC request id alongside the standard request/response pair.

The gateway dispatches one of two event classes depending on the JSON-RPC method:

- [`McpEvent`](#base-mcpevent) — for everything (initialize, tools/list, notifications/*, and any other JSON-RPC method).
- [`McpToolsCallEvent`](#mcptoolscallevent) — for `tools/call` invocations; the per-tool event `tools:call:<name>`. The gateway wraps the handler's response in the MCP `{ content, isError, structuredContent }` envelope.

## Origin

MCP events are always dispatched with the `mcp` [`OriginEnum`](:Zolinga Core:Events and Listeners) value. To opt in to MCP delivery, a listener must include `"mcp"` in its `origin` array (or the wildcard `"*"`).

## Base `McpEvent`

### Construction

```php
$event = new McpEvent(
    type: 'tools:list',
    jsonrpcId: 1,
    request: [],
    response: ['tools' => [...]]
);
```

| Property     | Type                              | Notes |
|--------------|-----------------------------------|-------|
| `type`       | `string`                          | The colon-converted JSON-RPC `method` (e.g. `tools/list` → `tools:list`; `notifications/initialized` → `notifications:initialized`). |
| `jsonrpcId`  | `string\|int\|null`               | The JSON-RPC `id`. `null` indicates a notification. |
| `request`    | `ArrayAccess\|array`              | The JSON-RPC `params` payload. |
| `response`   | `ArrayAccess\|array`              | Populate this with whatever the JSON-RPC `result` should be. The gateway serializes it under `result` as-is. |

The event is `StoppableInterface` — calling `$event->stopPropagation()` skips remaining listeners, just like for other Zolinga events.

### Status → JSON-RPC Error Code

For plain `McpEvent` (non-`tools/call`), the gateway maps `$event->status` to a JSON-RPC `error.code`:

| Status                     | JSON-RPC code | Meaning |
|----------------------------|---------------|---------|
| `OK`, 2xx, 3xx             | —             | Result is returned. |
| Undetermined (no listener) | -32601        | Method not found. |
| `BAD_REQUEST`              | -32602        | Invalid params. |
| `NOT_FOUND`, `NOT_IMPLEMENTED` | -32601    | Method not found. |
| `UNAUTHORIZED`, `FORBIDDEN`| -32600        | Invalid request. |
| Anything >= 500            | -32603        | Internal error. |

## `McpToolsCallEvent`

`McpToolsCallEvent` extends `McpEvent` and is dispatched for every `tools/call` invocation as the per-tool event `tools:call:<name>` (where `<name>` is the JSON-RPC `params.name` argument). The gateway always wraps the handler's response in the MCP `{ content, isError, structuredContent }` envelope, never in a JSON-RPC `error` block.

### Tool name validation

`<name>` must match `[A-Za-z0-9_-]{1,64}` (enforced by `McpHelper::isValidToolName()`). The same rule applies in two places so the manifest and the wire contract stay in sync:

- A `tools/call` request with a `name` that fails the check is rejected with a JSON-RPC `-32602 Invalid params` error before the event is dispatched.
- A listener whose declared `event` is `tools:call:<name>` with a non-conforming `<name>` is skipped by `tools/list` (and logged via `$api->log->error('system:mcp', ...)`) so it is neither advertised nor callable.

Pick a name from `[A-Za-z0-9_-]{1,64}` (the convention used by Claude Desktop, Cursor, and other major MCP clients) and stick to it.

### What handlers do and don't set

- **Set `$event->response`** to the **raw structured payload** (the object that conforms to the tool's `outputSchema`). The gateway copies it verbatim into `result.structuredContent`.
- **Optionally call `$event->addTextContent('...')`** to add a human-readable text block to `result.content`. When the handler adds no blocks, the gateway falls back to a single text block carrying `json_encode($response)` so legacy clients that only read `content[0].text` still receive the structured data.
- **Call `$event->setStatus(...)`** to signal success or failure. Non-OK status (or `UNDETERMINED` when no listener handled the event) becomes `result.isError = true`; the message ends up in `result.content[0].text`.
- **Do NOT** build the `{ content, isError, structuredContent }` envelope yourself — the gateway does that.

### Status handling

| Status set by handler | Envelope result |
|-----------------------|-----------------|
| `OK` (or 2xx/3xx)     | `isError: false`, `structuredContent` populated, `content[0].text` = JSON of `response` (or handler's added text) |
| `BAD_REQUEST` (400) or any 4xx/5xx | `isError: true`, `content[0].text` = handler's `addTextContent` text (or `event->message` if none), `structuredContent` omitted when empty |
| `UNDETERMINED` (no listener) | Gateway promotes to `NOT_FOUND` with message `"Unknown tool: <name>"`, `isError: true` |

### Example: minimal echo tool

```php
use Zolinga\System\Events\{ListenerInterface, McpToolsCallEvent};
use Zolinga\System\Types\StatusEnum;

class MyEchoHandler implements ListenerInterface
{
    public function onEcho(McpToolsCallEvent $event): void
    {
        $message = $event->request['message'] ?? '';
        if (!is_string($message) || $message === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing or empty "message" argument.');
            $event->addTextContent('Missing or empty "message" argument.');
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

### Example: tool with both structured data and a human-readable text

```php
public function onSearch(McpToolsCallEvent $event): void
{
    $query = $event->request['query'] ?? '';
    $results = $this->search($query);

    // Structured payload (conforms to outputSchema).
    $event->response = [
        'hits' => $results,
        'count' => count($results),
    ];
    // Human-readable rendering (optional but recommended).
    $event->addTextContent(sprintf('Found %d result(s) for "%s".', count($results), $query));

    $event->setStatus(StatusEnum::OK, 'OK');
}
```

### Manifest entry

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

The `schema.response` declaration is **required** for the tool to be exposed in `tools/list` — `McpTools::collectTools()` skips any tool without one and logs an error.

## See Also

- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [Events and Listeners](:Zolinga Core:Events and Listeners)
- [Event Authorization](:Zolinga Core:Events and Listeners:Event Authorization)
