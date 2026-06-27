---
name: system-create-mcp-tool
description: Use when exposing a Zolinga event handler as an MCP (Model Context Protocol) tool — i.e. a method clients can invoke via JSON-RPC `tools/call`. Covers handler class, manifest binding, JSON Schemas, the `McpEvent` contract, and the `schema.response` requirement enforced by `McpTools::collectTools()`.
argument-hint: "<module-name> <tool-name> [goal]"
---

# Zolinga Create MCP Tool

## Use When

- Exposing a piece of business logic as a callable MCP tool (JSON-RPC `tools/call`).
- Adding a new entry to the `tools/list` catalogue the MCP gateway returns.
- Wiring an existing handler into MCP delivery (most existing handlers can be reused as-is).

## Quick Anatomy

A tool is the **combination** of:

- A handler class implementing `ListenerInterface` with a method typed against `McpEvent`.
- A manifest entry with `event: "tools:call:<name>"` and `origin: ["mcp"]`.
- A `schema.response` JSON Schema (required) and an optional `schema.request`.
- The tool name visible to clients is the part after `tools:call:` in the event name.

The gateway (`McpServer`) expands JSON-RPC `tools/call` `params.name` into the event `tools:call:<name>`, dispatches it with `params.arguments` as the event request, and wraps the handler's response in the MCP `{ content, isError, structuredContent }` envelope. Handlers **never** build the envelope themselves.

## Workflow

### 1. Pick the tool name

The tool name is the string clients pass as `params.name`. It must be unique across the catalogue. Convention: lower-case, kebab-friendly, no leading namespace (`echo`, `search`, `ipd-checkout`, etc. — the `tools:call:` prefix is added by the gateway and stripped in `tools/list`).

### 2. Author the JSON Schemas

Place both files in `<module>/schema/mcp/`:

- `<tool>-request.json` — describes `params.arguments` (what clients send). Becomes `inputSchema` in `tools/list`.
- `<tool>-response.json` — **required**; describes the raw object the handler sets on `$event->response`. Becomes `outputSchema` in `tools/list` and is the contract `McpTools::collectTools()` validates against.

Use [JSON Schema 2020-12](https://json-schema.org/draft/2020-12/schema). The handler's `$event->response` MUST conform to this schema — clients will validate `result.structuredContent` against it.

### 3. Create the handler class

```php
<?php
declare(strict_types=1);

namespace MyModule\Mcp;

use Zolinga\System\Events\{ListenerInterface, McpEvent};
use Zolinga\System\Types\StatusEnum;

final class MyToolHandler implements ListenerInterface
{
    public function onMyTool(McpEvent $event): void
    {
        // 1. Validate $event->request (the JSON-RPC params.arguments).
        $arg = $event->request['arg'] ?? null;
        if (!is_string($arg) || $arg === '') {
            $event->setStatus(StatusEnum::BAD_REQUEST, 'Missing or empty "arg" argument.');
            return;
        }

        // 2. Set the raw structured payload (must match schema.response).
        $event->response = [
            'echo' => $arg,
            'receivedAt' => date('c'),
        ];

        // 3. Signal success.
        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

Rules:

- Set `$event->response` to the **raw structured payload** — never to the `{ content, isError, structuredContent }` envelope. The gateway builds the envelope.
- For errors, set a non-OK status (e.g. `BAD_REQUEST`, `NOT_FOUND`) with a descriptive message. The message ends up in `result.content[0].text`. Do not throw — the gateway logs the throw and turns it into a generic 500.
- If the handler is generic and you want to short-circuit, call `$event->stopPropagation()`. Other Zolinga listeners on the same event will be skipped.

### 4. Register the listener in `<module>/zolinga.json`

```json
{
  "listen": [
    {
      "event": "tools:call:my-tool",
      "class": "\\MyModule\\Mcp\\MyToolHandler",
      "method": "onMyTool",
      "origin": ["mcp"],
      "description": "One-sentence human description for the tools/list catalogue.",
      "schema": {
        "request":  "module://my-module/schema/mcp/my-tool-request.json",
        "response": "module://my-module/schema/mcp/my-tool-response.json"
      }
    }
  ]
}
```

- `event` MUST start with `tools:call:` — that's the routing convention. Anything else is ignored by `tools/list`.
- `origin: ["mcp"]` is required (or use `"*"` if you also want the listener to fire for non-MCP origins).
- `schema.response` is **required**; tools without it are skipped by `tools/list` and `$api->log->error()` is called. `schema.request` is optional.
- Add the event to the `emit` block too: `{"event": "tools:call:*", "class": "\\Zolinga\\System\\Events\\McpEvent", "origin": ["mcp"]}`. The wildcard covers every `tools:call:<name>` your module might dispatch.

### 5. Bump version and reload

1. Bump the module's `version` in `zolinga.json` (any patch+).
2. Add a CHANGELOG entry.
3. Run `bin/zolinga` (no parameters) to apply changes and regenerate the merged manifest cache.
4. When making requests, inspect also response HTTP headers, they may contain important information like 401 WWW-Authenticate challenges etc.

### 6. Smoke-test

```bash
# Discover
curl -X POST http://localhost:8080/mcp/ -D /dev/stderr \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | jq '.result.tools[].name'

# Invoke
curl -X POST http://localhost:8080/mcp/ -D /dev/stderr \
  -H 'Content-Type: application/json' \
  -d '{
    "jsonrpc":"2.0","id":2,"method":"tools/call",
    "params":{"name":"my-tool","arguments":{"arg":"hello"}}
  }' | jq
```

Expected success response:

```json
{
  "jsonrpc":"2.0","id":2,
  "result":{
    "content":[{"type":"text","text":"..."}],
    "isError":false,
    "structuredContent":{...}
  }
}
```

Expected error response (still in `result`, not a JSON-RPC `error` block):

```json
{
  "jsonrpc":"2.0","id":2,
  "result":{
    "content":[{"type":"text","text":"Missing or empty \"arg\" argument."}],
    "isError":true
  }
}
```

## Common Pitfalls

- **Throwing instead of `setStatus(BAD_REQUEST)`**: throws become generic 500s and the handler's user-friendly message is lost. Use `setStatus()` for client errors.
- **Setting `$event->response` to the envelope shape**: the gateway will wrap the wrapper, producing `{ result: { content, isError, structuredContent: { content, isError, structuredContent } } }`. Don't.
- **Forgetting `schema.response`**: `tools/list` will silently skip the tool. Check the system log (`data/system/logs/messages.log`) for an `$api->log->error()` line naming the tool.
- **Returning a non-conforming response**: clients validate `structuredContent` against `outputSchema`; if it doesn't match, they reject the result. Keep the handler and the schema in sync.
- **Missing PHP `declare(strict_types=1);` or missing `use Zolinga\System\Types\StatusEnum`**: standard PHP code-quality nits that the linter will catch.

## References

- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP) — endpoint overview, request/response shape, headers.
- [MCP Events](:Zolinga Core:Events and Listeners:MCP) — full `McpEvent` reference, status → envelope mapping, handler examples.
- [`tools/call` event](:ref:event:tools/call) — per-event reference page.
- [`tools/list` event](:ref:event:tools/list) — catalogue reference; documents the `schema.response` requirement.
- [system-create-handler](system-skills:system-create-handler) — generic listener creation (origin filtering, event naming).
- [system-authoring-manifest](system-skills:system-authoring-manifest) — manifest conventions.
- [system-php-coding-style](system-skills:system-php-coding-style) — listener class style.
- [system-documentation](system-skills:system-documentation) — when to add WIKI docs (always for a new public feature like an MCP tool).
