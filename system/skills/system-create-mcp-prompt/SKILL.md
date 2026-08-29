---
name: system-create-mcp-prompt
description: Use when creating or updating MCP prompts — reusable prompt templates exposed to MCP clients via `prompts/list` and `prompts/get`. Covers `.meta.json` definitions, tenant filtering, `content.uri` file references, `{{arg}}` substitution, and the `mcp-system` scheme.
argument-hint: "<module-name> <prompt-name> [goal]"
---

# Zolinga Create MCP Prompt

## Use When

- Exposing a reusable prompt template to MCP clients as a discoverable prompt.
- Creating multi-message conversation templates with `{{arg}}` placeholder substitution.
- Referencing large text files from prompt definitions via `content.uri`.
- Deciding whether a prompt belongs to the base MCP endpoint or a tenant-scoped MCP route.

## How It Works

MCP clients discover prompts via `prompts/list` and retrieve them via `prompts/get`. Zolinga supports two ways to provide prompts:

1. **Static**: Drop a `.meta.json` definition in `modules/<module>/mcp/prompts/`. The system discovers it automatically — no manifest changes needed.
2. **Dynamic**: Hook the `mcp:prompts/list` event and add prompts programmatically.

For static prompts, the filename (without `.meta.json`) becomes the prompt identifier, rewritten to `mcp-system:<module>:<basename>`. The `messages` array is stripped from the list response (metadata-only) and only served by `prompts/get`.

Tenant-aware MCP routes work like this:

- `/mcp` and `/mcp/oauth` use the base prompt events such as `mcp:prompts/list` and `mcp:prompts/get:mcp-system`.
- `/mcp/oauth/{tenant}` appends `@{tenant}` to the dispatched event type, e.g. `mcp:prompts/list@admin` and `mcp:prompts/get:mcp-system@admin`.
- Static prompt definitions can opt into specific tenant routes with an optional `tenants` array. Missing or invalid `tenants` behaves like `[""]`, the base `/mcp` route. Tenant routes inherit parent definitions, so `/mcp/oauth/admin` also sees prompts defined for `/mcp`. Nested tenants inherit too: `/mcp/oauth/admin/users` sees `admin` and `/mcp`. See [MCP Tenants](:Zolinga Core:MCP:Tenants).

## 1. Static Prompts

### Place the `.meta.json` definition

The `.meta.json` IS the prompt — it contains metadata AND the `messages` array directly. No separate content file is needed for inline text.

```
modules/my-module/mcp/prompts/trademark-search.meta.json
```

### Simple text prompt (inline)

```json
{
  "title": "Trademark Search",
  "description": "Prompt template for trademark similarity search.",
  "arguments": [
    { "name": "query", "description": "The trademark text to search for", "required": true }
  ],
  "messages": [
    {
      "role": "user",
      "content": {
        "type": "text",
        "text": "Search for trademarks similar to \"{{query}}\"."
      }
    }
  ]
}
```

Note: `name` is omitted — the filename (`trademark-search`) is the identifier. If present and doesn't match the filename, a warning is logged.

### Tenant-scoped static prompt

```json
{
  "title": "Admin Review",
  "tenants": ["admin"],
  "messages": [
    {
      "role": "user",
      "content": {
        "type": "text",
        "text": "Review the latest admin alerts."
      }
    }
  ]
}
```

This prompt appears in `prompts/list` on `/mcp/oauth/admin` and nested tenants such as `/mcp/oauth/admin/users`. It is hidden from `/mcp`. `prompts/get` from a tenant that does not inherit `admin` is rejected.

### Text from file (for large prompts)

For large text bodies, use `content.uri` to reference a file within the module:

```json
{
  "title": "Code Review",
  "description": "Asks the LLM to analyze code quality.",
  "arguments": [
    { "name": "code", "description": "The code to review", "required": true }
  ],
  "messages": [
    {
      "role": "user",
      "content": {
        "type": "text",
        "uri": "module://my-module/mcp/prompts/code-review-template.md"
      }
    }
  ]
}
```

The handler reads the file at `uri` (must use `module://` scheme, must resolve within the module directory), puts its contents into `content.text`, removes `uri`, then does `{{arg}}` substitution.

### `.meta.json` Fields

| Field | Required | Description |
|-------|----------|-------------|
| `title` | no | Human-readable title. |
| `description` | no | One-line description (also included in `prompts/get` response). |
| `arguments` | no | Array of `{ name, description, required }`. |
| `icons` | no | Array of icon objects. |
| `tenants` | no | Array of tenant names this prompt is published on. Missing or invalid behaves like `[""]` (base `/mcp`, inherited by tenant routes). |
| `messages` | yes (for `prompts/get`) | Array of `{ role, content }` — stripped from `prompts/list`. |
| `name` | no (ignored for static) | Filename is the identifier. If present and mismatched, a warning is logged. |

## 2. Dynamic Prompts

For prompts generated at runtime, hook `mcp:prompts/list` and add prompts programmatically.

### 2a. List handler — advertise prompts

```php
<?php
declare(strict_types=1);

namespace MyModule\Mcp;

use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\Mcp\Prompts\ListEvent;
use Zolinga\System\Types\StatusEnum;

final class MyPromptsListHandler implements ListenerInterface
{
    public function onList(ListEvent $event): void
    {
        $event->addPrompt(
            name: 'mcp-my-module:daily-summary',
            title: 'Daily Summary',
            description: 'Generated daily summary prompt.',
            arguments: [['name' => 'date', 'required' => true]]
        );
        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

To advertise prompts only on `/mcp/oauth/admin`, register the listener for `mcp:prompts/list@admin` instead of the base `mcp:prompts/list` event.

### 2b. Get handler — serve prompt messages

The `GetEvent` constructor extracts the scheme from `params.name` and appends it to the event type. So a prompt with name `mcp-my-module:daily-summary` triggers event `mcp:prompts/get:mcp-my-module` on the base routes, or `mcp:prompts/get:mcp-my-module@admin` on `/mcp/oauth/admin`.

```php
final class MyPromptsGetHandler implements ListenerInterface
{
    public function onGet(GetEvent $event): void
    {
        $event->response = [
            'messages' => [
                ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Summarize for ' . $event->request['arguments']['date']]]
            ]
        ];
        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

### 2c. Register both in `zolinga.json`

```json
{
  "listen": [
    {
      "event": "mcp:prompts/list",
      "class": "\\MyModule\\Mcp\\MyPromptsListHandler",
      "method": "onList",
      "origin": ["mcp"]
    },
    {
      "event": "mcp:prompts/get:mcp-my-module",
      "class": "\\MyModule\\Mcp\\MyPromptsGetHandler",
      "method": "onGet",
      "origin": ["mcp"]
    }
  ]
}
```

For tenant-specific prompts, use `@tenant` suffixed event names in the manifest.

## Testing

```bash
# List all prompts on the base route
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"prompts/list"}' | jq

# List prompts on a tenant route
curl -X POST https://your-host/mcp/oauth/admin \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <token>' \
  -d '{"jsonrpc":"2.0","id":1,"method":"prompts/list"}' | jq

# Get a prompt
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"prompts/get","params":{"name":"mcp-system:my-module:trademark-search","arguments":{"query":"ACME"}}}' | jq
```

## References

- [MCP Prompts wiki](:Zolinga Core:MCP:Prompts)
- [MCP Tenants wiki](:Zolinga Core:MCP:Tenants)
- `system/src/Mcp/McpPromptsListHandler.php` — static prompt discovery + tenant filtering.
- `system/src/Mcp/McpPromptsGetHandler.php` — `mcp-system` get handler + tenant enforcement.
- `system/src/Events/Mcp/Prompts/ListEvent.php` — `addPrompt()` / `addPromptJson()` API.
- `system/src/Events/Mcp/Prompts/GetEvent.php` — scheme extraction and validation.