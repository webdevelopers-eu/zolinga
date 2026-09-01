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

## Access Control

Add a `right` property to the listener in `zolinga.json` to restrict a tool to authorized callers. When the caller is not authorized, the tool is omitted from `tools/list` and `tools/call` returns `FORBIDDEN`.

```json
{
  "event": "my-module-search",
  "class": "\\MyModule\\Mcp\\SearchHandler",
  "method": "onSearch",
  "origin": ["mcp"],
  "right": "member of users",
  "schema": { ... }
}
```

- The `right` value is any expression accepted by `$api->isAuthorized()` (e.g. `"member of users"`, a role name, a comma-separated list).
- Tools without `right` are public — accessible to anyone, including unauthenticated callers.

### OAuth Scopes as Rights

For MCP clients authenticating via OAuth 2.0, the `right` value can use the `oauth2:scope <scope-name>` format to require a specific OAuth scope. For example:

```json
"right": "oauth2:scope administration"
```

This checks whether the caller has the `oauth2:scope administration` right, which is granted in two ways:

1. **OAuth token scope**: The `BearerTokenListener` in `zolinga-oauth` converts each space-separated scope in the access token into a corresponding `oauth2:scope <scope>` right at the `system:authorize` event. So a token with scope `administration` authorizes the right `oauth2:scope administration`.
2. **RMS right**: If the user has an explicit RMS right `oauth2:scope administration` stored in the database, it is also authorized by the RMS `UserService::onAuthorize` listener.

This mechanism works identically in `zolinga.json` listener `right` properties and in `.meta.json` `zolinga.right` fields (for resources and prompts) — both are checked via `$api->isAuthorized()`.

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


# Related

{{MCP Related}}
