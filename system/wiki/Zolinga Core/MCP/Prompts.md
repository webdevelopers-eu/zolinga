# MCP Prompts

MCP prompts are reusable prompt templates that clients can retrieve via `prompts/list` and `prompts/get`.

## How It Works

- **Discovery**: `prompts/list` scans each module's `mcp/prompts/*.meta.json` files.
- **Identification**: The filename (without `.meta.json`) becomes the prompt identifier, rewritten to `mcp-system:<module>:<basename>`.
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
