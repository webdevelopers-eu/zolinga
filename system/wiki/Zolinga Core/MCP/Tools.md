# MCP Tools

Expose any Zolinga event as an [MCP](https://modelcontextprotocol.io/) tool that AI assistants and MCP clients can discover and invoke.

## How It Works

1. Add `"mcp"` to a listener's `origin` array in your module's `zolinga.json`.
2. Declare `schema.request` and `schema.response` (JSON Schema files) — `schema.response` is **required** for the tool to appear in `tools/list`.
3. The listener's event name becomes the tool name. Clients call it via `tools/call` with `params.name`.

## Manifest Entry

```json
{
  "event": "my-module-search",
  "class": "\\MyModule\\Mcp\\SearchHandler",
  "method": "onSearch",
  "origin": ["mcp"],
  "description": "Search the database.",
  "schema": {
    "request": "module://my-module/schema/mcp/search-request.json",
    "response": "module://my-module/schema/mcp/search-response.json"
  }
}
```

## Handler Example

```php
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

        $event->response = [
            'hits' => $this->search($query),
            'count' => count($hits),
        ];
        $event->setStatus(StatusEnum::OK, 'OK');
    }
}
```

The handler sets the **raw structured payload** on `$event->response`. The gateway wraps it in the MCP `{ content, isError, structuredContent }` envelope automatically.

## Tool Name Rules

- Must match `[A-Za-z0-9_:-]{1,64}`
- Must not start with `mcp:` (reserved for protocol events)
- The colon is allowed so Zolinga event names (e.g. `my-module:search`) work verbatim

## Testing

```bash
# List available tools
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'

# Call a tool
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"my-module-search","arguments":{"query":"test"}}}'
```

## See Also

- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [MCP Events](:Zolinga Core:Events and Listeners:MCP)
- [MCP Resources](:Zolinga Core:MCP:Resources)
- [`tools/list` event reference](:ref:event:tools/list)
- [`tools/call` event reference](:ref:event:tools/call)
