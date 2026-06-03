# MCP (Model Context Protocol)

Expose any Zolinga event as an [MCP](https://modelcontextprotocol.io/) tool. The endpoint at `public/mcp/index.php` accepts JSON-RPC 2.0 requests from MCP clients, dispatches them as [`\Zolinga\System\Events\McpEvent`](:Zolinga Core:Events and Listeners:MCP) objects with the `mcp` origin, and serializes the response back as a JSON-RPC 2.0 message.

This is a non-streaming implementation of MCP — every request returns a single JSON-RPC response. The endpoint is HTTP `POST` only.

# Quick Start

Send an `initialize` request:

```bash
curl -X POST http://localhost:8080/mcp/ \
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

The response is the lifecycle initialization payload (protocolVersion, capabilities, serverInfo, instructions). It does **not** list tools — call `tools/list` for that.

Discover available tools:

```bash
curl -X POST http://localhost:8080/mcp/ \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
```

Call a tool by name:

```bash
curl -X POST http://localhost:8080/mcp/ \
  -H 'Content-Type: application/json' \
  -d '{
    "jsonrpc":"2.0",
    "id": 3,
    "method": "tools/call",
    "params": { "name": "echo", "arguments": { "message": "Hello MCP" } }
  }'
```

The gateway translates the JSON-RPC method to a Zolinga event by replacing `/` with `:`. For `tools/call` specifically, it expands the method into the per-tool event `tools:call:<name>` (where `<name>` is `params.name`) and passes `params.arguments` as the event request. So this request dispatches a `McpToolsCallEvent` with `type = "tools:call:echo"` and `request = {"message": "Hello MCP"}`. The tool handler sets the **raw structured payload** on `$event->response` (it must conform to the tool's `outputSchema`); the gateway wraps it in the MCP `{ content, isError, structuredContent }` envelope.

Response (per the [MCP `tools/call` spec](https://modelcontextprotocol.io/specification/2025-06-18/server/tools)):

```json
{
  "jsonrpc": "2.0",
  "id": 3,
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

# Exposing a Listener as an MCP Tool

Add `mcp` to the listener's `origin` array in your module's `zolinga.json`. The
event name must follow the `tools:call:<tool-name>` convention; that name
becomes the JSON-RPC `tools/call` `params.name` value:

```json
{
  "event": "tools:call:ipdefender-search",
  "class": "\\Ipdefender\\Mcp\\SearchHandler",
  "method": "onSearch",
  "origin": ["mcp"],
  "description": "Search the trademark database.",
  "schema": {
    "request":  "module://ipdefender/schema/mcp/search-request.json",
    "response": "module://ipdefender/schema/mcp/search-response.json"
  }
}
```

- `origin: ["mcp"]` — opt in to MCP delivery.
- `event: "tools:call:<name>"` — the `<name>` part is the JSON-RPC tool name. Clients invoke it via `tools/call` with `params.name = "<name>"`.
- `schema.request` / `schema.response` — each value is a [Zolinga URI](:Zolinga Core:Paths and Zolinga URI) that resolves to a JSON Schema file. The MCP `tools/list` response embeds the parsed schema as `inputSchema` / `outputSchema`. **`schema.response` is required** for the tool to be exposed by `tools/list` — `McpTools` logs an error and skips the tool when it is missing.

The handler class implements [`ListenerInterface`](:Zolinga Core:Events and Listeners) and receives a [`McpToolsCallEvent`](:Zolinga Core:Events and Listeners:MCP) instead of a plain `RequestEvent`. It sets the raw structured payload on `$event->response`; the gateway builds the MCP envelope:

```php
namespace Ipdefender\Mcp;

use Zolinga\System\Events\{ListenerInterface, McpToolsCallEvent};
use Zolinga\System\Types\StatusEnum;

class SearchHandler implements ListenerInterface
{
    public function onSearch(McpToolsCallEvent $event): void
    {
        $query = $event->request['query'] ?? null;
        if (!is_string($query) || $query === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing "query" argument.');
            $event->addTextContent('Missing "query" argument.');
            return;
        }

        $hits = $this->search($query);

        // Raw structured payload — must conform to your outputSchema.
        $event->response = [
            'hits' => $hits,
            'count' => count($hits),
        ];
        // Optional human-readable text.
        $event->addTextContent(sprintf('Found %d result(s) for "%s".', count($hits), $query));

        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

# Reserved MCP Events

The `McpTools` collector excludes these reserved event names from the tool list — they are MCP protocol events, not user-callable tools:

- `initialize`
- `tools:list`
- `tools:call:*` (any per-tool sub-event)
- `notifications:*`

# Method-to-Event Mapping

The gateway rewrites every JSON-RPC `method` into a Zolinga event `type`. For most methods this is a slash-to-colon substitution; `tools/call` is expanded to a per-tool event with the tool name appended:

| JSON-RPC `method`           | Zolinga event `type`         | `request` source        |
|-----------------------------|------------------------------|-------------------------|
| `initialize`                | `initialize`                 | full `params`           |
| `tools/list`                | `tools:list`                 | full `params`           |
| `tools/call` (name=foo)     | `tools:call:foo`             | `params.arguments`      |
| `notifications/initialized` | `notifications:initialized`  | full `params`           |

# JSON-RPC Mapping

For non-`tools/call` methods (initialize, tools/list, notifications/*, etc.):

| JSON-RPC 2.0                | Zolinga |
|----------------------------|---------|
| `method` (string)          | event `type` (see Method-to-Event Mapping) |
| `params` (object/array)    | `$event->request` (ArrayObject) |
| `id` (string/int/null)     | `$event->jsonrpcId` |
| response `result`          | `$event->response` (verbatim) |
| response `error.code`      | derived from `$event->status` (see Error Mapping) |
| `notifications/*` (no id)  | dispatched, no reply sent |

For `tools/call` invocations, the gateway dispatches a [`McpToolsCallEvent`](:Zolinga Core:Events and Listeners:MCP):

| JSON-RPC 2.0                | Zolinga |
|----------------------------|---------|
| `method`                   | `tools/call` (always) |
| `params.name`              | appended to event `type` as `tools:call:<name>` |
| `params.arguments`         | `$event->request` |
| response `result`          | gateway-built envelope `{ content, isError, structuredContent }` (see [MCP Events](:Zolinga Core:Events and Listeners:MCP)) |
| `isError: true`            | gateway sets when handler's `$event->status` is non-OK (or `UNDETERMINED`); message lands in `result.content[0].text` |

# Error Mapping

For non-`tools/call` events:

| `StatusEnum`              | JSON-RPC `error.code` | Meaning |
|---------------------------|----------------------|---------|
| `BAD_REQUEST` (400)       | -32602               | Invalid params |
| `NOT_FOUND` / `NOT_IMPLEMENTED` (404 / 501) | -32601 | Method not found |
| `UNAUTHORIZED` / `FORBIDDEN` (401 / 403) | -32600 | Invalid request |
| undetermined (no listener handled the event) | -32601 | Method not found |
| anything else (>= 500)    | -32603               | Internal error |
| `OK` (or 2xx/3xx)         | — (no `error` block) | success |

For `tools:call:*` events, the gateway never emits a JSON-RPC `error` block. A non-OK status (or `UNDETERMINED` because no listener handled the event) becomes `result.isError = true` with the handler's message in `result.content[0].text` (per the MCP `tools/call` spec). An undetermined event is promoted to `NOT_FOUND` with the text `"Unknown tool: <name>"`.

# Protocol Headers

Every response carries:

- `Content-Type: application/json; charset=utf-8`
- `MCP-Protocol-Version: 2025-06-18`

# Schema Locations

JSON Schema files referenced from MCP listener manifests are loaded with the plain `module://{module}/schema/{path}` URI. They live in the conventional `schema/` subfolder of a module (e.g. `module://ipdefender/schema/mcp/search-request.json` resolves to `modules/ipdefender/schema/mcp/search-request.json`).

# Batching

The endpoint accepts a JSON-RPC 2.0 batch (an array of requests). The response is an array of responses; if the entire batch consists of notifications, the response is HTTP 204 No Content.

# Architecture

| Class | Purpose |
|-------|---------|
| [`McpServer`](:ref:class:Zolinga\System\Mcp\McpServer) | Stateful per-request orchestrator: parses the body, dispatches, sends the reply. Thin JSON-RPC-to-Zolinga translator: each JSON-RPC `method` becomes an event `type` by replacing `/` with `:`. `tools/call` is expanded into the per-tool event `tools:call:<name>` (where `<name>` is `params.name`) with `params.arguments` as the event request; the dispatch uses [`McpToolsCallEvent`](:Zolinga Core:Events and Listeners:MCP) so the gateway can wrap the response in the MCP envelope. |
| [`McpRequestValidator`](:ref:class:Zolinga\System\Mcp\McpRequestValidator) | Validates JSON-RPC 2.0 envelopes. |
| [`McpInitializeHandler`](:ref:class:Zolinga\System\Mcp\McpInitializeHandler) | Listens to the `initialize` event; returns the lifecycle payload. |
| [`McpTools`](:ref:class:Zolinga\System\Mcp\McpTools) | `onList` for `tools:list`; returns the tool catalogue. Excludes any tool without a `schema.response` declaration. |
| [`McpHelper`](:ref:class:Zolinga\System\Mcp\McpHelper) | Misc helpers (status → error code, response normalization, `envelope()` for `McpToolsCallEvent`). |
| `Exceptions\McpException` + subclasses | Top-level errors (`McpParseErrorException`, `McpInvalidRequestException`, `McpMethodNotFoundException`, `McpInvalidParamsException`, `McpInternalErrorException`). |

# Security

Configure your web server so that `/mcp/` is reachable only by trusted origins. The endpoint is unauthenticated by default; use the `right` field on a listener manifest entry and an [`AuthorizeEvent`](:Zolinga Core:Events and Listeners:Authorization) provider to gate access to specific tools.

# See Also

- [`\Zolinga\System\Events\McpEvent`](:Zolinga Core:Events and Listeners:MCP)
- [Paths and Zolinga URI](:Zolinga Core:Paths and Zolinga URI)
- [MCP specification](https://modelcontextprotocol.io/specification/2025-06-18)
