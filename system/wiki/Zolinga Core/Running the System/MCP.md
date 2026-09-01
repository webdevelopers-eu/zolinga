# MCP (Model Context Protocol)

Expose Zolinga events as [MCP](https://modelcontextprotocol.io/) tools and module files as MCP resources. The gateway accepts JSON-RPC 2.0 requests from MCP clients, dispatches them as [`\Zolinga\System\Events\Mcp\McpEvent`](:Zolinga Core:Events and Listeners:MCP) objects (one concrete subclass per JSON-RPC method) with the `mcp` origin, and serializes the response back as a JSON-RPC 2.0 message.

Two endpoint forms are available:

- **`/mcp`** — mixed authentication. Some tools require authentication, others are public. Use this for general-purpose access.
- **`/mcp/oauth`** — always requires authentication. Every request returns HTTP 401 until the client authenticates. Use this when your MCP client expects uniform authentication across all requests (e.g. Hermes).

The gateway supports two MCP capability areas:

- **Tools** — expose any Zolinga event as a callable tool. See [MCP Tools](:Zolinga Core:MCP:Tools).
- **Resources** — expose module files (docs, images, etc.) as discoverable resources. See [MCP Resources](:Zolinga Core:MCP:Resources).

This is a non-streaming implementation of MCP — every request returns a single JSON-RPC response. Both endpoints are HTTP `POST` only.

# Quick Start

These examples use the `/mcp` endpoint (mixed authentication). Replace it with `/mcp/oauth` if you need all requests to require authentication.

Send an `initialize` request:

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

The response is the lifecycle initialization payload (protocolVersion, capabilities, serverInfo, instructions). It does **not** list tools — call `tools/list` for that.

Discover available tools:

```bash
curl -X POST http://localhost:8080/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
```

Call a tool by name:

```bash
curl -X POST http://localhost:8080/mcp \
  -H 'Content-Type: application/json' \
  -d '{
    "jsonrpc":"2.0",
    "id": 3,
    "method": "tools/call",
    "params": { "name": "echo", "arguments": { "message": "Hello MCP" } }
  }'
```

The gateway translates the JSON-RPC method to a base Zolinga event by replacing `/` with `:`. For `tools/call` specifically, it uses the bare tool name (`params.name`) as the base event type and passes `params.arguments` as the event request. So this request dispatches a `Tools\CallEvent` with `type = "echo"` on `/mcp`. The tool handler sets the **raw structured payload** on `$event->response` (it must conform to the tool's `outputSchema`); the gateway wraps it in the MCP `{ content, isError, structuredContent }` envelope.

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

## Using the OAuth Endpoint

The `/mcp/oauth` endpoint requires a valid OAuth Bearer token for all requests. Without authentication, you receive HTTP 401:

```bash
curl -X POST http://localhost:8080/mcp/oauth \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' \
  -D-
```

Response includes the `WWW-Authenticate` header with OAuth metadata:

```
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Bearer resource_metadata="https://your-domain.com/.well-known/oauth-protected-resource/mcp"
```

After completing the OAuth flow and obtaining an access token, include it in the `Authorization` header:

```bash
curl -X POST http://localhost:8080/mcp/oauth \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <your-access-token>' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

See [OAuth Authorization](:Zolinga OAuth:Authorization) for the complete OAuth flow.

# Exposing a Listener as an MCP Tool

Add `mcp` to the listener's `origin` array in your module's `zolinga.json`. The
listener's event name is the tool name; clients invoke it via `tools/call`
with `params.name` set to that event name:

```json
{
  "event": "my-module-search",
  "class": "\\MyModule\\Mcp\\SearchHandler",
  "method": "onSearch",
  "origin": ["mcp"],
  "description": "Search the database.",
  "schema": {
    "request":  "module://my-module/schema/mcp/search-request.json",
    "response": "module://my-module/schema/mcp/search-response.json"
  }
}
```

- `origin: ["mcp"]` — opt in to MCP delivery.
- `event: "<name>"` — the event name is the JSON-RPC tool name. Clients invoke it via `tools/call` with `params.name = "<name>"`.
- `schema.request` / `schema.response` — each value is a [Zolinga URI](:Zolinga Core:Paths and Zolinga URI) that resolves to a JSON Schema file. The MCP `tools/list` response embeds the parsed schema as `inputSchema` / `outputSchema`. **`schema.response` is required** for the tool to be exposed by `tools/list` — `McpToolsListHandler` logs an error and skips the tool when it is missing.

On `/mcp/oauth`, clients still call the same tool name, and the gateway dispatches the event as `<name>`.

The handler class implements [`ListenerInterface`](:Zolinga Core:Events and Listeners) and receives a [`Tools\CallEvent`](:Zolinga Core:Events and Listeners:MCP) with `type = "<name>"`. It sets the raw structured payload on `$event->response`; the gateway builds the MCP envelope:

```php
namespace MyModule\\Mcp;

use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\Tools\CallEvent;
use Zolinga\System\Types\StatusEnum;

class SearchHandler implements ListenerInterface
{
    public function onSearch(CallEvent $event): void
    {
        $query = $event->request['query'] ?? null;
        if (!is_string($query) || $query === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing "query" argument.');
            return;
        }

        $hits = $this->search($query);

        // Raw structured payload — must conform to your outputSchema.
        $event->response = [
            'hits' => $hits,
            'count' => count($hits),
        ];

        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

# Reserved MCP Events

On the base routes (`/mcp` and `/mcp/oauth`), all non-`tools/call` MCP events are prefixed with `mcp:` by the gateway (e.g. `mcp:initialize`, `mcp:tools/list`, `mcp:notifications/*`). The `McpToolsListHandler` collector excludes any event whose name starts with `mcp:` from the tool list — they are MCP protocol events, not user-callable tools. `McpHelper::isValidToolName()` also explicitly rejects names starting with `mcp:` so user tools can never collide with the protocol prefix, even though `:` is now an allowed character in tool names.

# Method-to-Event Mapping

The gateway rewrites every JSON-RPC `method` into a base Zolinga event `type`. For most methods this is a slash-to-colon substitution; `tools/call` is expanded to a per-tool event with the tool name appended.

| JSON-RPC `method`           | Zolinga event `type`         | `request` source        |
|-----------------------------|------------------------------|-------------------------|
| `initialize`                | `mcp:initialize`             | full `params`           |
| `tools/list`                | `mcp:tools/list`             | full `params`           |
| `tools/call` (name=foo)     | `foo`                        | `params.arguments`      |
| `notifications/initialized` | `mcp:notifications/initialized`  | full `params`           |

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

For `tools/call` invocations, the gateway dispatches a [`Tools\CallEvent`](:Zolinga Core:Events and Listeners:MCP) with `type = "<name>"`:

| JSON-RPC 2.0                | Zolinga |
|----------------------------|---------|
| `method`                   | `tools/call` (always) |
| `params.name`              | used verbatim as event `type` |
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

For `tools/call` events, the gateway never emits a JSON-RPC `error` block. A non-OK status (or `UNDETERMINED` because no listener handled the event) becomes `result.isError = true` with the handler's message in `result.content[0].text` (per the MCP `tools/call` spec). An undetermined event is promoted to `NOT_FOUND` with the text `"Unknown tool: <name>"`.

# Protocol Headers

Every response carries:

- `Content-Type: application/json; charset=utf-8`
- `MCP-Protocol-Version: 2025-06-18`

# Schema Locations

JSON Schema files referenced from MCP listener manifests are loaded with the plain `module://{module}/schema/{path}` URI. They live in the conventional `schema/` subfolder of a module (e.g. `module://my-module/schema/mcp/search-request.json` resolves to `modules/my-module/schema/mcp/search-request.json`).

# Batching

Not supported. This is a non-streaming implementation of the MCP Streamable HTTP spec, which uses one JSON-RPC message per HTTP request. A top-level JSON array is rejected with a `-32600 Invalid request` error ("Batches are not supported").

# Architecture

| Class | Purpose |
|-------|---------|
| [`McpServer`](:ref:class:Zolinga\System\Mcp\McpServer) | Stateful per-request orchestrator: parses the body, dispatches, sends the reply. Thin JSON-RPC-to-Zolinga translator: each JSON-RPC `method` becomes a base event `type` by replacing `/` with `:`. `tools/call` uses the bare tool name (`params.name`) as the base event `type` with `params.arguments` as the event request. |
| [`Mcp\McpEvent`](:ref:class:Zolinga\System\Events\Mcp\McpEvent) | Abstract base event for all MCP JSON-RPC requests. `McpEvent::fromJsonRpc()` validates the envelope, resolves the concrete subclass (`InitializeEvent`, `Tools\ListEvent`, `Tools\CallEvent`, `Prompts\*`, `Resources\*`). |
| [`McpInitializeHandler`](:ref:class:Zolinga\System\Mcp\McpInitializeHandler) | Listens to the base `mcp:initialize` event and returns the lifecycle payload. |
| [`McpToolsListHandler`](:ref:class:Zolinga\System\Mcp\McpToolsListHandler) | `onList` for the base `mcp:tools/list` event; returns the default tool catalogue. |
| [`McpHelper`](:ref:class:Zolinga\System\Mcp\McpHelper) | Misc helpers (status → error code, response normalization, `envelope()` for `tools/call` results). |
| `Exceptions\McpException` + subclasses | Top-level errors (`McpParseErrorException`, `McpInvalidRequestException`, `McpMethodNotFoundException`, `McpInvalidParamsException`, `McpInternalErrorException`). |

# Choosing an Endpoint

Use **`/mcp/oauth`** when:

- Your MCP client expects OAuth authentication before listing tools (e.g. Hermes).
- You want every request to require authentication — no public tool access.
- The client triggers OAuth flow by receiving an HTTP 401 response with a `WWW-Authenticate: Bearer` challenge.

Use **`/mcp`** when:

- You want to expose some tools publicly without authentication.
- Your MCP client supports per-tool authentication (checks the `right` field in the tool definition).
- You prefer mixed access: public discovery with protected tools.

The endpoint choice affects authentication only:

| Endpoint              | Authentication                                         | Dispatched event type |
|-----------------------|--------------------------------------------------------|-----------------------|
| `/mcp`                | Per-tool. Public tools work without auth.              | Base type (`mcp:initialize`, `echo`) |
| `/mcp/oauth`          | Always. Returns HTTP 401 until client authenticates.   | Base type (`mcp:initialize`, `echo`) |

Configure your MCP client with the appropriate endpoint URL:

```bash
# For clients that need uniform authentication
https://your-domain.com/mcp/oauth

# For general-purpose access with mixed authentication
https://your-domain.com/mcp
```

# Security

Configure your web server so that `/mcp` and `/mcp/oauth` are reachable only by trusted origins. Use the `right` field on a listener manifest entry and an [`AuthorizeEvent`](:Zolinga Core:Events and Listeners:Authorization) provider to gate access to specific tools.

The `/mcp/oauth` endpoint enforces OAuth authentication at the gateway level — unauthenticated requests receive HTTP 401 with a `WWW-Authenticate: Bearer` header pointing to the OAuth authorization server metadata. Clients must complete the OAuth flow before accessing any tools.

The `/mcp` endpoint allows per-tool authentication — tools without a `right` field are publicly accessible; tools with a `right` field require a valid OAuth Bearer token in the `Authorization` header. See [OAuth Authorization](:Zolinga OAuth:Authorization) for configuration details.


# Related

{{MCP Related}}
