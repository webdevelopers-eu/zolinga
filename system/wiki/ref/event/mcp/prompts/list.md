# `mcp:prompts/list` Event

The MCP `prompts/list` JSON-RPC method. Dispatched by the [MCP gateway](:Zolinga Core:Running the System:MCP) as a [`Prompts\ListEvent`](:Zolinga Core:Events and Listeners:MCP) with the `mcp` origin.

The system-provided [`\Zolinga\System\Mcp\McpPromptsListHandler::onList()`](:ref:class:Zolinga\\System\\Mcp\\McpPromptsListHandler) handles this event. It scans each module's `mcp/prompts/*.meta.json` files, rewrites the prompt `name` to `mcp-system:<module>:<basename>`, strips the `messages` array (list response is metadata-only), and returns the prompt descriptors.

## Request

`params` is optional. The MCP spec defines an optional `cursor` for pagination, but this non-streaming gateway does not paginate and ignores it.

## Response (set on `$event->response`)

The response is a `{ prompts: [...] }` payload, where each entry has:

| Field         | Type   | Notes |
|---------------|--------|-------|
| `name`        | `string` | External identifier in `mcp-system:<module>:<basename>` format. |
| `title`       | `string` | Human-readable title (optional, from `.meta.json`). |
| `description` | `string` | One-line description (optional, from `.meta.json`). |
| `arguments`   | `array`  | Array of `{ name, description, required }` (optional, from `.meta.json`). |
| `icons`       | `array`  | Icon descriptors (optional, from `.meta.json`). |

The `messages` array is **never** included in the list response — it is stripped by `addPromptJson()`. Use `prompts/get` to retrieve messages.

Prompts are sorted by `name` for deterministic output.

## Example

```bash
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"prompts/list"}'
```

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "prompts": [
      {
        "name": "mcp-system:ipdefender:trademark-search",
        "title": "Trademark Search",
        "description": "Prompt template for trademark similarity search.",
        "arguments": [
          { "name": "query", "description": "The trademark text to search for", "required": true }
        ]
      }
    ]
  }
}
```

## Adding Prompts Programmatically

The `ListEvent` exposes `addPromptJson()` and `addPrompt()` for handlers that build the prompt list themselves. Both validate the `name` against the allowed scheme whitelist (`mcp-*` only) and strip `messages`/`uri` fields:

```php
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\Mcp\Prompts\ListEvent;
use Zolinga\System\Types\StatusEnum;

class MyPromptsListHandler implements ListenerInterface
{
    public function onList(ListEvent $event): void
    {
        $event->addPrompt(
            name: 'mcp-system:my-module:dynamic-prompt',
            title: 'Dynamic Prompt',
            description: 'Generated at runtime.',
            arguments: [['name' => 'topic', 'required' => true]]
        );
        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

## See Also

- [MCP Prompts](:Zolinga Core:MCP:Prompts)
- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [`mcp:prompts/get:*` event](:ref:event:mcp/prompts/get)