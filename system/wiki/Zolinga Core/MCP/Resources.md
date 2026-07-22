# MCP Resources

Expose static or dynamic files from your module to MCP clients (AI assistants, IDE extensions, etc.) as discoverable resources. Clients call `resources/list` to see what is available and `resources/read` to fetch the contents.

Resources are advertised via `.meta.json` descriptor files placed in your module's `mcp/resources/` directory. The system automatically discovers them and serves their contents — no manifest changes needed.

## Quick Start

1. Create a content file in `modules/<your-module>/mcp/resources/`:

```bash
# e.g. modules/my-module/mcp/resources/guide.md
echo "# User Guide\n\nWelcome to my module." > modules/my-module/mcp/resources/guide.md
```

2. Create a `.meta.json` descriptor next to it (same basename + `.meta.json`):

```json
// modules/my-module/mcp/resources/guide.md.meta.json
{
  "uri": "module://my-module/mcp/resources/guide.md",
  "name": "guide.md",
  "title": "User Guide",
  "description": "Getting started guide for my module.",
  "mimeType": "text/markdown"
}
```

3. That's it. The resource is now discoverable via `resources/list` and readable via `resources/read`.

## The `.meta.json` Format

| Field | Required | Description |
|-------|----------|-------------|
| `uri` | yes | Zolinga `module://` path to the actual content file |
| `name` | yes | Unique identifier for the resource (typically the filename) |
| `title` | no | Human-readable title |
| `description` | no | One-line description |
| `mimeType` | no | MIME type; determines `text` vs `blob` response format |
| `icons` | no | Array of icon objects (`src`, `mimeType`, `sizes`) |

Extra fields are allowed and passed through to the client.

## The `mcp-system` URI Scheme

Internal Zolinga paths (like `module://ipdefender/mcp/resources/about.md`) are never sent to MCP clients. Instead, the system rewrites them to the external scheme:

```
mcp-system:<module>:<basename>
```

For example, `module://ipdefender/mcp/resources/about.md` becomes `mcp-system:ipdefender:about.md` on the wire. This prevents leaking internal file paths and enforces path parsing for security.

## Text vs Binary Resources

The `mimeType` field in `.meta.json` determines how the content is returned:

- **`text/*`** MIME types (e.g. `text/markdown`, `text/plain`, `text/html`) → returned as `{ "text": "..." }`
- **All other** MIME types (e.g. `image/png`, `application/pdf`) → returned as `{ "blob": "<base64>" }`

## Example: Binary Resource

```json
// modules/my-module/mcp/resources/logo.png.meta.json
{
  "uri": "module://my-module/mcp/resources/logo.png",
  "name": "logo.png",
  "title": "Module Logo",
  "mimeType": "image/png"
}
```

The `resources/read` response will contain a base64-encoded `blob` field.

## Testing with curl

```bash
# List all resources
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"resources/list"}'

# Read a resource by its mcp-system URI
curl -X POST https://your-host/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"resources/read","params":{"uri":"mcp-system:my-module:guide.md"}}'
```

## Security

- Internal Zolinga paths are never exposed to clients; all resource URIs are rewritten to `mcp-system:<module>:<basename>`.
- Directory traversal is blocked: `basename()` is applied to both module and basename components, and the result must match the raw input.
- Only URI schemes in the `ResourcesEvent::ALLOWED_URI_SCHEMES` whitelist (`mcp-system`, `http`, `https`) are accepted in responses.

## See Also

- [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP)
- [MCP Events](:Zolinga Core:Events and Listeners:MCP)
- [MCP Tools](:Zolinga Core:MCP:Tools)
