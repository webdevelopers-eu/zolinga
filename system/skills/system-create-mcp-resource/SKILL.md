---
name: system-create-mcp-resource
description: Use when creating or updating MCP resources — static files exposed to MCP clients via `resources/list` and `resources/read`. Covers `.meta.json` descriptors, URI rewriting, dynamic resources via `mcp:resources/list` event hook, and the `mcp-system` scheme.
argument-hint: "<module-name> <resource-name> [goal]"
---

# Zolinga Create MCP Resource

## Use When

- Exposing a static file (Markdown doc, image, JSON data) to MCP clients as a discoverable resource.
- Registering dynamic (non-file) resources by hooking `mcp:resources/list`.
- Understanding URI rewriting that prevents internal Zolinga paths from leaking to clients.

## How It Works

MCP clients discover resources via `resources/list` and fetch them via `resources/read`. Zolinga supports two ways to provide resources:

1. **Static**: Drop a `.meta.json` descriptor in `modules/<module>/mcp/resources/`. The system discovers it automatically — no manifest changes needed.
2. **Dynamic**: Hook the `mcp:resources/list` event and add resources programmatically.

For static resources, the system rewrites the internal `module://` URI to `mcp-system:<module>:<basename>` so the real file path is never exposed. For dynamic resources, the developer provides the external URI directly — the system validates the scheme is allowed (`mcp-*`, `http`, `https`) but does not rewrite it. In both cases, internal `module://` paths never reach the client.

## 1. Static Resources

### Place the content file + `.meta.json` descriptor

The content file can live **anywhere** in the module. The `.meta.json` must live in `mcp/resources/` and its `uri` field points to the content file via a `module://` path.

```
modules/my-module/mcp/resources/guide.md          <-- content file
modules/my-module/mcp/resources/guide.md.meta.json <-- descriptor (same basename + .meta.json)
```

```json
// modules/my-module/mcp/resources/guide.md.meta.json
{
  "uri": "module://my-module/mcp/resources/guide.md",
  "name": "guide.md",
  "title": "User Guide",
  "description": "Getting started guide.",
  "mimeType": "text/markdown"
}
```

The `uri` can point to any file in the module, not just files in `mcp/resources/`:

```json
{
  "uri": "module://my-module/docs/api-reference.md",
  "name": "api-reference.md",
  "title": "API Reference",
  "mimeType": "text/markdown"
}
```

Done. The resource is discoverable immediately. No `zolinga.json` changes, no version bumps.

### What clients see

The internal `module://` URI is rewritten to `mcp-system:<module>:<basename>` on the wire:

| Internal (in `.meta.json`) | External (client sees) |
|---|---|
| `module://ipdefender/mcp/resources/about.md` | `mcp-system:ipdefender:about.md` |
| `module://my-module/docs/api.md` | `mcp-system:my-module:api.md` |

### `.meta.json` Fields

| Field | Required | Description |
|-------|----------|-------------|
| `uri` | yes | Zolinga `module://` path to the content file. Can point anywhere in the module. |
| `name` | yes | Unique resource identifier (typically the filename). |
| `title` | no | Human-readable title. |
| `description` | no | One-line description. |
| `mimeType` | no | `text/*` → returned as `text`, everything else → base64 `blob`. Defaults to `application/octet-stream`. |
| `icons` | no | Array of `{ src, mimeType, sizes }`. |

Extra fields pass through to the client unchanged.

## 2. Dynamic Resources

For resources that are not files (database records, computed data, live API responses), hook `mcp:resources/list` and add resources programmatically.

### 2a. List handler — advertise resources

```php
<?php
declare(strict_types=1);

namespace MyModule\Mcp;

use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\Resources\ListEvent;

final class MyResourcesHandler implements ListenerInterface
{
    public function onList(ListEvent $event): void
    {
        // Convenience method
        $event->addResource(
            uri: 'mcp-my-module:daily-report',
            name: 'daily-report',
            title: 'Daily Report',
            description: 'Generated daily report.',
            mimeType: 'text/plain'
        );

        // Or addResourceJson() for full control over extra fields
        $event->addResourceJson([
            'uri' => 'https://example.com/live-data.json',
            'name' => 'live-data',
            'title' => 'Live Data Feed',
            'mimeType' => 'application/json',
        ]);
    }
}
```

### 2b. Read handler — serve resource contents

The `ReadEvent` constructor extracts the URI scheme and appends it to the event type. So a resource with URI `mcp-my-module:daily-report` triggers event `mcp:resources/read:mcp-my-module`. Register a handler for that event:

```php
final class MyResourcesReadHandler implements ListenerInterface
{
    public function onRead(ReadEvent $event): void
    {
        $uri = $event->request['uri'];
        // Fetch/generate content, then:
        $event->response = ['contents' => [
            ['uri' => $uri, 'mimeType' => 'text/plain', 'text' => 'Report content...']
        ]];
        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

### 2c. Register both in `zolinga.json`

```json
{
  "listen": [
    {
      "event": "mcp:resources/list",
      "class": "\\MyModule\\Mcp\\MyResourcesHandler",
      "method": "onList",
      "origin": ["mcp"]
    },
    {
      "event": "mcp:resources/read:mcp-my-module",
      "class": "\\MyModule\\Mcp\\MyResourcesReadHandler",
      "method": "onRead",
      "origin": ["mcp"]
    }
  ]
}
```

Bump module `version` and run `bin/zolinga` to apply.

## Allowed URI Schemes

`ResourcesEvent::ALLOWED_URI_SCHEMES` = `['mcp-*', 'http', 'https']`.

The **sole purpose** of this whitelist is security: preventing server-side file paths from leaking to clients, and preventing attackers from abusing resource URIs to read arbitrary server-side paths. By rejecting `module://`, `file://`, and other internal schemes, the system forces you to invent a client-facing URI that maps to your secret server-side path. The client sees an opaque URI like `mcp-my-module:daily-report`, and only your read handler decides what that maps to internally (`module://my-module/data/reports/2026-07-22.md`, a database query, etc.). Without this enforcement, a client could craft `module://system/config/local.json` or `file:///etc/passwd` and read anything. The whitelist makes that impossible — the URI is just a key, not a path.

- **`mcp-*`** — any scheme starting with `mcp-` (e.g. `mcp-system`, `mcp-my-module`, `mcp-anything`). Use this for all custom resources. The URI is opaque to the client; your read handler decides what it maps to internally.
- **`http` / `https`** — for resources already served at public URLs (no path to hide).
- **`module://`** — internal only, never sent to clients. The system rewrites it to `mcp-system:...` for static resources.

The `mcp-*` wildcard means you can invent any `mcp-<name>` scheme for your dynamic resources without modifying the whitelist.

## Text vs Binary

The `mimeType` determines the response format:

- **`text/*`** → `{ "uri": "...", "mimeType": "...", "text": "<contents>" }`
- **Everything else** → `{ "uri": "...", "mimeType": "...", "blob": "<base64>" }`

## Testing

```bash
# List all resources
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"resources/list"}' | jq

# Read a resource
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"resources/read","params":{"uri":"mcp-system:my-module:guide.md"}}' | jq
```

## References

- [MCP Resources wiki](:Zolinga Core:MCP:Resources)
- `system/src/Mcp/McpResourcesListHandler.php` — static resource discovery + URI rewriting.
- `system/src/Mcp/McpResourcesReadHandler.php` — `mcp-system` read handler.
- `system/src/Events/Mcp/Resources/ListEvent.php` — `addResource()` / `addResourceJson()` API.
- `system/src/Events/Mcp/Resources/ReadEvent.php` — scheme-based event dispatch.
- `system/src/Events/Mcp/Resources/ResourcesEvent.php` — `ALLOWED_URI_SCHEMES` with `mcp-*` wildcard.
- [system-create-mcp-tool](system-skills:system-create-mcp-tool) — for callable tools (different MCP primitive).