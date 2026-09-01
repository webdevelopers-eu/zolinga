# MCP Prompts

MCP prompts are reusable prompt templates that clients can retrieve via `prompts/list` and `prompts/get`.

## How It Works

- **Discovery**: `prompts/list` scans each module's `mcp/prompts/*.meta.json` files and includes all descriptors.
- **Identification**: The filename (without `.meta.json`) becomes the prompt identifier, rewritten to `mcp-system://<module>/prompts/<basename>`.
- **Retrieval**: `prompts/get` reads the `.meta.json`, resolves file references, applies `{{arg}}` substitution, and returns the `messages` array.

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
| `messages` | yes (for `prompts/get`) | Array of `{ role, content }` — stripped from `prompts/list` |
| `zolinga.right` | no | Access restriction; any `$api->isAuthorized()` expression (e.g. `"member of users"`). See [Access Control](#access-control). |

## Wire Format

### `prompts/list` response (metadata only, no `messages`)

```json
{
  "prompts": [
    {
      "name": "mcp-system://ipdefender/prompts/trademark-search",
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

## Access Control

Any `.meta.json` descriptor can restrict access to authenticated users by adding a `zolinga.right` field. This works the same way as the `right` property on listeners in `zolinga.json` — the caller must satisfy the declared right or the prompt is hidden from `prompts/list` and `prompts/get` returns `FORBIDDEN`.

```json
// modules/my-module/mcp/prompts/internal-review.meta.json
{
  "title": "Internal Review",
  "description": "Prompt template for internal code review.",
  "arguments": [
    { "name": "code", "required": true }
  ],
  "messages": [
    { "role": "user", "content": { "type": "text", "text": "Review this code: {{code}}" } }
  ],
  "zolinga": {
    "right": "member of users"
  }
}
```

- The `zolinga.right` value is any expression accepted by `$api->isAuthorized()` (e.g. `"member of users"`, a role name, a comma-separated list).
- When the caller is not authorized, the prompt is **omitted** from `prompts/list` and `prompts/get` returns a `FORBIDDEN` status.
- The `zolinga` block is stripped from the response before it reaches the client, so the right expression is never leaked.
- Prompts without `zolinga.right` are public — accessible to anyone, including unauthenticated callers.

## Security

- `name` parsing uses `basename()` checks — directory traversal (`../`) is blocked.
- Module existence is explicitly checked against `$api->manifest->moduleNames`.
- `content.uri` must use `module://` scheme; resolved `realpath()` must be within the module directory.
- `messages` and internal `uri` fields are stripped from `prompts/list` responses.
- Access can be restricted per prompt via `zolinga.rights` in the `.meta.json` (see Access Control above).

## Dynamic Prompts

Handlers can add prompts programmatically via the `mcp:prompts/list` event:

```php
$event->add(
    name: 'mcp-custom:my-module:dynamic-prompt',
    title: 'Dynamic Prompt',
    description: 'Generated at runtime',
    arguments: [['name' => 'q', 'required' => true]]
);
```

# Related

{{MCP Related}}
