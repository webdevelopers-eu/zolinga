---
name: system-convert-remote-event-to-mcp-tool
description: Use when exposing an existing `origin: ["remote"]` listener as an MCP tool without rewriting the handler. Covers the one-line manifest pattern that re-uses the same class+method, plus the minimal schema work needed to satisfy `McpTools::collectTools()`. Complements system-create-mcp-tool (which covers the greenfield case).
---

# Convert a `remote` Event into an MCP Tool

## Use When

- A `remote` web event already returns the exact payload you want to expose.
- The handler does not need a new method, different arguments, or a different response shape.
- You want the tool in `tools/list` and dispatchable via `tools/call` with zero handler code changes.

If the response shape, auth, or origin needs to change, use [system-create-mcp-tool](system-skills:system-create-mcp-tool) instead.

## The Pattern (1 manifest entry + 2 schema files)

### 1. Manifest

Add a second `listen` entry that points at the **same** class+method as the `remote` one, with `origin: ["mcp"]` and a `tools:call:<name>` event:

```json
{
  "event": "example:pricing:list",
  "class": "Example\\App\\Api\\AlertApi",
  "method": "onCountries",
  "origin": ["remote"]
},
{
  "event": "tools:call:getPricingList",
  "description": "<human-readable description for the tools/list catalogue>",
  "class": "Example\\App\\Api\\AlertApi",
  "method": "onCountries",
  "origin": ["mcp"],
  "schema": {
    "request":  "module://<module>/schemas/<name>Request.json",
    "response": "module://<module>/schemas/<name>Response.json"
  }
}
```

The gateway expands `tools/call` `params.name` into `tools:call:<name>` and runs the listener with `params.arguments` as the event `request`. The handler runs unchanged.

### 2. Schemas (place in `<module>/schemas/` — NOT `schema/mcp/`)

**`schemas/<name>Request.json`** — mirror the event's `request` keys exactly. Include every key the handler reads from `$event->request`; mark optional ones by leaving them out of `required`.

**`schemas/<name>Response.json`** — mirror the `$event->response` shape the handler actually sets. Both `data` and its sub-objects go under `properties`. Use `additionalProperties: false` on every object node so the gateway rejects drift.

### 3. Bump version and reload

Bump the module `version` and run `bin/zolinga` (no parameters). The manifest cache regenerates; the new tool appears in the next `tools/list` response.

## Schema Gotchas

### Empty `properties` becomes `[]` on the wire

`McpTools::loadSchema()` does `json_decode($contents, true)`. An empty JSON `{}` becomes an empty PHP array, which `json_encode` re-serialises as `[]`. Strict MCP clients then reject the tool with *"Incorrect type. Expected 'object'"*.

**Fix** — always give `properties` at least one entry, even if no-op:

```json
{
  "type": "object",
  "properties": {
    "_": {
      "type": "null",
      "description": "Reserved placeholder. The tool takes no arguments; send {}."
    }
  },
  "additionalProperties": false
}
```

Clients that send `arguments: {}` validate fine (the `_` property is optional and absent), and the gateway emits a JSON object on the wire.

### Response must include every field the handler emits

If the handler returns `search` on each country, the response schema must list it (or set `additionalProperties: true`). Otherwise strict clients reject `structuredContent` as non-conforming — even though `tools/call` itself succeeded.

### Schema dir location is part of the manifest, not the convention

`module://<module>/schemas/...` and `module://<module>/schema/mcp/...` are both valid Zolinga URIs. The dir is whatever you point at in `zolinga.json`; pick one and keep all your tools' schemas in the same place.

## When the Pattern Is Not Enough

Add a real handler class (back to [system-create-mcp-tool](system-skills:system-create-mcp-tool)) when you need any of:

- Different arguments than the `remote` event accepts.
- Different response shape (e.g. strip a field, wrap in `{ data: ... }`).
- Different auth (the `remote` event may have `"right": "member of users"`; the MCP version probably should not).
- Per-tool caching, validation, or side-effects before/after the `remote` call.

## Smoke Test

```bash
# Discover
curl -X POST http://localhost:8080/mcp/ -D /dev/stderr -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | jq '.result.tools[].name'

# Invoke (no args example)
curl -X POST http://localhost:8080/mcp/ -D /dev/stderr -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"getPricingList","arguments":{}}}' | jq
```

A successful invocation returns:

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

## Checklist

- [ ] Manifest entry re-uses existing class+method; only `event`, `origin`, `schema` differ.
- [ ] `schema.request` lists every key the handler reads from `$event->request`.
- [ ] `schema.response` lists every key the handler writes to `$event->response`, with `additionalProperties: false`.
- [ ] `properties` is never literally `{}` — use the `_` placeholder if the tool is no-arg.
- [ ] Module version bumped, `bin/zolinga` re-run, `tools/list` shows the new tool.
- [ ] `tools/call` invocation returns `isError: false` and `structuredContent` matches the response schema.
