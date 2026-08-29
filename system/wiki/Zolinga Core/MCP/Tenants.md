# MCP Tenants

Give a named MCP client extra tools, prompts, and resources without hiding the shared catalogue.

The gateway has three URL forms:

- `/mcp` — mixed authentication, base catalogue
- `/mcp/oauth` — the same base catalogue, always authenticated
- `/mcp/oauth/{tenant}` — authenticated, named surface such as `/mcp/oauth/admin`

## Inheritance

A tenant route sees the tools, prompts, and resources defined for itself and for every parent tenant, including the base `/mcp` route (`""`).

- `/mcp/oauth/admin` sees everything defined for `/mcp`, plus anything published for `admin`.
- `/mcp/oauth/admin/users` sees everything defined for `/mcp` and for `admin`, plus anything published for `admin/users`.
- `/mcp` sees only items defined for the base route. It does not see `admin`-only items.

| You defined it for | `/mcp` | `/mcp/oauth/admin` | `/mcp/oauth/admin/users` |
|--------------------|--------|--------------------|--------------------------|
| (default, no tenant) | yes | yes | yes |
| `admin` | no | yes | yes |
| `admin/users` | no | no | yes |
| `other` | no | no | no |

`/mcp` and `/mcp/oauth` share the same base tenant. The difference is authentication, not the catalogue.

## Prompts and resources

Static prompts and resources take an optional `tenants` list in their `.meta.json` file.

Omit `tenants`, or set `"tenants": [""]`, to publish on `/mcp`. Those items are inherited by every tenant route.

```json
{
  "title": "Admin Review",
  "tenants": ["admin"],
  "messages": [
    {
      "role": "user",
      "content": { "type": "text", "text": "Review the latest admin alerts." }
    }
  ]
}
```

That prompt appears on `/mcp/oauth/admin` and on nested tenants such as `/mcp/oauth/admin/users`. It does not appear on `/mcp`. The same rule is enforced for `prompts/get` and `resources/read`: a tenant can fetch an item only when that tenant would also see it in the list.

See [MCP Prompts](:Zolinga Core:MCP:Prompts) and [MCP Resources](:Zolinga Core:MCP:Resources) for the file format.

## Tools

Tools use the `@tenant` suffix on the event name, not a `tenants` list. Inheritance is the same.

- `event: "echo"` — published on `/mcp`, inherited by every tenant route.
- `event: "echo@admin"` — published on `/mcp/oauth/admin` and nested tenants such as `/mcp/oauth/admin/users`. Hidden from `/mcp`.

Clients still call the tool as `echo`. The gateway dispatches `echo@admin` on `/mcp/oauth/admin`, then falls back to parent listeners, so a base `echo` handler still runs there unless a more specific `echo@admin` listener exists.

See [MCP Tools](:Zolinga Core:MCP:Tools).

# Related

{{MCP Related}}
