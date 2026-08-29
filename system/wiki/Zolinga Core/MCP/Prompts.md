# MCP Prompts

MCP prompts are reusable prompt templates that clients can retrieve via `prompts/list` and `prompts/get`.

## How It Works

- **Discovery**: `prompts/list` scans each module's `mcp/prompts/*.meta.json` files and includes only descriptors whose `tenants` list contains the current MCP tenant.
- **Identification**: The filename (without `.meta.json`) becomes the prompt identifier, rewritten to `mcp-system:<module>:<basename>`.
- **Retrieval**: `prompts/get` reads the `.meta.json`, checks the same `tenants` filter, resolves file references, applies `{{arg}}` substitution, and returns the `messages` array. When the prompt exists but the tenant is not allowed, the request is rejected.

## Creating a Prompt

Place a `.meta.json` file in `modules/<your-module>/mcp/prompts/`:

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

The `name` field is omitted for static prompts — the filename is the identifier.

If you want a prompt to appear only on a tenant-scoped MCP route, add a `tenants` array:

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

Missing `tenants` is treated as `[""]`, which means the prompt belongs to the default non-tenant route. The same rule is enforced by both `prompts/list` and `prompts/get`.

### Text from file (for large prompts)

```json
{
  "title": "Code Review",
  "description": "Analyze code quality.",
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

### Multi-message conversation

```json
{
  "title": "Debate",
  "arguments": [{ "name": "topic", "required": true }],
  "messages": [
    { "role": "user", "content": { "type": "text", "text": "Let's debate: {{topic}}" } },
    { "role": "assistant", "content": { "type": "text", "text": "I'll argue the positive side." } }
  ]
}
```

## Fields

| Field | Required | Description |
|-------|----------|-------------|
| `title` | no | Human-readable title |
| `description` | no | One-line description (included in `prompts/get` response) |
| `arguments` | no | Array of `{ name, description, required }` |
| `tenants` | no | Array of tenant names allowed to see the prompt in `prompts/list`; missing means `[""]` |
| `messages` | yes (for `prompts/get`) | Array of `{ role, content }` — stripped from `prompts/list` |

## Wire Format

### `prompts/list` response (metadata only, no `messages`)

```json
{
  "prompts": [
    {
      "name": "mcp-system:ipdefender:trademark-search",
      "title": "Trademark Search",
      "description": "Prompt template for trademark similarity search.",
      "arguments": [{ "name": "query", "description": "...", "required": true }]
    }
  ]
}
```

### `prompts/get` response

```json
{
  "description": "Prompt template for trademark similarity search.",
  "messages": [
    { "role": "user", "content": { "type": "text", "text": "Search for \"ACME\"." } }
  ]
}
```

## Security

- `name` parsing uses `basename()` checks — directory traversal (`../`) is blocked.
- Module existence is explicitly checked against `$api->manifest->moduleNames`.
- `content.uri` must use `module://` scheme; resolved `realpath()` must be within the module directory.
- `messages` and internal `uri` fields are stripped from `prompts/list` responses.
- The optional `tenants` field is consumed by the gateway for both `prompts/list` and `prompts/get` filtering and is not exposed to clients.
- `prompts/get` returns forbidden when the prompt exists but is not allowed for the current tenant route.

## Dynamic Prompts

Handlers can add prompts programmatically via the `mcp:prompts/list` event:

```php
$event->addPrompt(
    name: 'mcp-custom:my-module:dynamic-prompt',
    title: 'Dynamic Prompt',
    description: 'Generated at runtime',
    arguments: [['name' => 'q', 'required' => true]]
);
```

# Related

{{MCP Related}}
