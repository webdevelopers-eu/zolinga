# MCP Events

`\Zolinga\System\Events\Mcp\McpEvent` is the abstract base event class fired by the [MCP gateway](:Zolinga Core:Running the System:MCP) at `public/mcp/index.php`. It extends [`RequestResponseEvent`](:Zolinga Core:Events and Listeners) and carries the JSON-RPC request id alongside the standard request/response pair.

The gateway dispatches one concrete subclass per JSON-RPC method. `McpEvent::fromJsonRpc()` is the factory that validates the JSON-RPC 2.0 envelope and resolves the correct subclass via a `match` on the `method` field:

| JSON-RPC `method`     | Event class                                  | Event `type`                         |
|-----------------------|----------------------------------------------|--------------------------------------|
| `initialize`          | `Mcp\InitializeEvent`                        | `mcp:initialize`                     |
| `tools/list`          | `Mcp\Tools\ListEvent`                        | `mcp:tools/list`                     |
| `tools/call`          | `Mcp\Tools\CallEvent`                        | bare tool name (`params.name`)       |
| `prompts/list`        | `Mcp\Prompts\ListEvent`                      | `mcp:prompts/list`                   |
| `prompts/get`         | `Mcp\Prompts\GetEvent`                       | `mcp:prompts/get`                    |
| `resources/list`      | `Mcp\Resources\ListEvent`                    | `mcp:resources/list`                 |
| `resources/read`      | `Mcp\Resources\ReadEvent`                    | `mcp:resources/read:<scheme>`        |

The `resources/read` event type includes the URI scheme as a suffix (e.g. `mcp:resources/read:mcp-system`), allowing handlers to register for specific URI schemes. See [MCP Resources](:Zolinga Core:MCP:Resources) for details.
| anything else         | —                                            | `McpMethodNotFoundException` thrown  |

`Tools\CallEvent` is the only subclass whose `type` is not derived from the method name — it is the bare tool name (`params.name`) so the event dispatches to the tool's own listener. The other subclasses hard-code their `mcp:`-prefixed `type` in their constructor.

For `tools/call` the gateway wraps the handler's response in the MCP `{ content, isError, structuredContent }` envelope. For all other methods the gateway serializes `$event->response` verbatim as the JSON-RPC `result`. The gateway distinguishes a `tools/call` invocation by `instanceof Tools\CallEvent` (not by a flag or event-name prefix) and wraps the response accordingly.

## Origin

MCP events are always dispatched with the `mcp` [`OriginEnum`](:Zolinga Core:Events and Listeners) value. To opt in to MCP delivery, a listener must include `"mcp"` in its `origin` array (or the wildcard `"*"`).

## `McpEvent` (abstract base)

### Construction

You normally do not construct MCP events yourself — the gateway calls `McpEvent::fromJsonRpc()` for you. For testing or manual dispatch you can instantiate a concrete subclass directly:

```php
use Zolinga\System\Events\Mcp\Tools\CallEvent;

$event = new CallEvent(
    jsonrpcId: 1,
    params: ['name' => 'echo', 'arguments' => ['message' => 'hi']],
);
// $event->type === 'echo'
// $event->request === ['message' => 'hi']
```

| Property     | Type                              | Notes |
|--------------|-----------------------------------|-------|
| `type`       | `string`                          | The Zolinga event type. For protocol methods it is the method with `/` → `:` and prefixed with `mcp:` (e.g. `tools/list` → `mcp:tools/list`). For `tools/call` it is the bare tool name (`params.name`). Each concrete subclass sets this in its constructor. |
| `jsonrpcId`  | `string\|int\|null`               | The JSON-RPC `id`. `null` indicates a notification (no reply sent). |
| `request`    | `ArrayAccess\|array`              | The JSON-RPC `params` payload. For `tools/call` it is `params.arguments`. |
| `response`   | `ArrayAccess\|array`              | Populate this with whatever the JSON-RPC `result` should be. For plain events the gateway serializes it under `result` as-is. For `tools/call` it becomes `result.structuredContent`. |

The event is `StoppableInterface` — calling `$event->stopPropagation()` skips remaining listeners, just like for other Zolinga events.

### Status → JSON-RPC Error Code (non-`tools/call`)

For plain MCP events (anything that is not a `Tools\CallEvent`), the gateway maps `$event->status` to a JSON-RPC `error.code`:

| Status                     | JSON-RPC code | Meaning |
|----------------------------|---------------|---------|
| `OK`, 2xx, 3xx             | —             | Result is returned. |
| Undetermined (no listener) | -32601        | Method not found. |
| `BAD_REQUEST`              | -32602        | Invalid params. |
| `NOT_FOUND`, `NOT_IMPLEMENTED` | -32601    | Method not found. |
| `UNAUTHORIZED`, `FORBIDDEN`| -32600        | Invalid request. |
| Anything >= 500            | -32603        | Internal error. |

## `tools/call` events

For `tools/call` invocations the gateway dispatches a `Tools\CallEvent` with `type = "<name>"` (where `<name>` is the JSON-RPC `params.name` argument). The gateway always wraps the handler's response in the MCP `{ content, isError, structuredContent }` envelope, never in a JSON-RPC `error` block.

### Tool name validation

`<name>` must match `[A-Za-z0-9_:-]{1,64}` and must not start with `mcp:` (enforced by `McpHelper::isValidToolName()`). The colon is allowed so that Zolinga event names (e.g. `my-module:search`) can be used verbatim as MCP tool names. The `mcp:` prefix is reserved for protocol events. The same rule applies in two places so the manifest and the wire contract stay in sync:

- A `tools/call` request with a `name` that fails the check is rejected with a JSON-RPC `-32602 Invalid params` error before the event is dispatched.
- A listener whose declared `event` name (used as the tool name) is non-conforming is skipped by `tools/list` (and logged via `$api->log->error('system:mcp', ...)`) so it is neither advertised nor callable.

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
use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\Tools\CallEvent;
use Zolinga\System\Types\StatusEnum;

class MyEchoHandler implements ListenerInterface
{
    public function onEcho(CallEvent $event): void
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

The `schema.response` declaration is **required** for the tool to be exposed in `tools/list` — `McpToolsListHandler::collectTools()` skips any tool without one and logs an error.

## See Also

- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [Events and Listeners](:Zolinga Core:Events and Listeners)
- [Event Authorization](:Zolinga Core:Events and Listeners:Event Authorization)
