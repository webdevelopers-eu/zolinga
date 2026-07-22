# `mcp:resources/list` Event

The MCP `resources/list` JSON-RPC method. Dispatched by the [MCP gateway](:Zolinga Core:Running the System:MCP) as a [`Resources\ListEvent`](:Zolinga Core:Events and Listeners:MCP) with the `mcp` origin.

The system-provided [`\Zolinga\System\Mcp\McpResourcesListHandler::onList()`](:ref:class:Zolinga\\System\\Mcp\\McpResourcesListHandler) handles this event. It scans each module's `mcp/resources/*.meta.json` files, rewrites the internal `uri` to the external `mcp-system:<module>:<basename>` scheme, and returns the resource descriptors.

## Request

`params` is optional. The MCP spec defines an optional `cursor` for pagination, but this non-streaming gateway does not paginate and ignores it.

## Response (set on `$event->response`)

The response is a `{ resources: [...] }` payload, where each entry has at least:

| Field         | Type   | Notes |
|---------------|--------|-------|
| `uri`         | `string` | External URI in `mcp-system:<module>:<basename>` format. |
| `name`        | `string` | Unique resource identifier (typically the filename). |
| `title`       | `string` | Human-readable title (optional, from `.meta.json`). |
| `description` | `string` | One-line description (optional, from `.meta.json`). |
| `mimeType`    | `string` | MIME type (optional, from `.meta.json`). |
| `icons`       | `array`  | Icon descriptors (optional, from `.meta.json`). |

Resources are sorted by `uri` for deterministic output.

## Example

```bash
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"resources/list"}'
```

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "resources": [
      {
        "uri": "mcp-system:ipdefender:about.md",
        "name": "about.md",
        "title": "About IP Defender",
        "description": "Overview of the IP Defender SaaS platform.",
        "mimeType": "text/markdown"
      }
    ]
  }
}
```

## Adding Resources Programmatically

The `ListEvent` exposes `addResourceJson()` and `addResource()` for handlers that build the resource list themselves. Both validate the URI against the allowed scheme whitelist (`mcp-system`, `http`, `https`) and require a non-empty `name`:

```php
use Zolinga\System\Events\{ListenerInterface};
use Zolinga\System\Events\Mcp\Resources\ListEvent;
use Zolinga\System\Types\StatusEnum;

class MyResourcesListHandler implements ListenerInterface
{
    public function onList(ListEvent $event): void
    {
        $event->addResource(
            uri: 'mcp-system:my-module:dynamic-resource',
            name: 'dynamic-resource',
            title: 'Dynamic Resource',
            description: 'Generated at runtime.',
            mimeType: 'text/plain'
        );
        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

## See Also

- [MCP Resources](:Zolinga Core:MCP:Resources)
- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [`mcp:resources/read:*` event](:ref:event:mcp/resources/read)
