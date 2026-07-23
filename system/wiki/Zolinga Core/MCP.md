Priority: 0.7

# MCP (Model Context Protocol)

Zolinga speaks the [Model Context Protocol](https://modelcontextprotocol.io/) — an open standard that lets AI assistants and other MCP clients discover and call functionality on your server. The endpoint at `/mcp` accepts JSON-RPC 2.0 requests over HTTP `POST` and translates each one into a Zolinga event. Any module can expose its events as MCP tools, its files as MCP resources, and its prompt templates as MCP prompts — no extra framework required.

This is a **non-streaming** implementation: every request returns a single JSON-RPC response. Batching is not supported.

# What You Can Do

- **Expose tools** — Turn any Zolinga event into a callable tool that an AI client can discover via `tools/list` and invoke via `tools/call`. See [MCP Tools](:Zolinga Core:MCP:Tools).
- **Expose resources** — Serve static or dynamic files from your module (docs, images, config) as discoverable resources via `resources/list` and `resources/read`. See [MCP Resources](:Zolinga Core:MCP:Resources).
- **Expose prompts** — Provide reusable prompt templates that clients retrieve via `prompts/list` and `prompts/get`. See [MCP Prompts](:Zolinga Core:MCP:Prompts).

# Quick Start

Send an `initialize` request to begin a session:

```bash
curl -X POST http://localhost:8080/mcp \
  -H 'Content-Type: application/json' \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2025-06-18",
      "capabilities": {},
      "clientInfo": { "name": "my-client", "version": "1.0.0" }
    }
  }'
```

Then list the tools the server offers:

```bash
curl -X POST http://localhost:8080/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
```

Call a tool by name:

```bash
curl -X POST http://localhost:8080/mcp \
  -H 'Content-Type: application/json' \
  -d '{
    "jsonrpc":"2.0",
    "id":3,
    "method":"tools/call",
    "params":{"name":"echo","arguments":{"message":"Hello MCP"}}
  }'
```

# How It Fits Together

The gateway at `public/mcp/index.php` is a thin translator: each JSON-RPC `method` becomes a Zolinga event, the event is dispatched to listeners that opted in to the `mcp` origin, and the response is serialized back as a JSON-RPC 2.0 message. For `tools/call` the gateway wraps the handler's response in the MCP `{ content, isError, structuredContent }` envelope automatically.

For the full request/response flow, method-to-event mapping, and error handling see [Running the System: MCP](:Zolinga Core:Running the System:MCP). For the event class hierarchy and listener contract see [Events and Listeners: MCP](:Zolinga Core:Events and Listeners:MCP).

# Sub-Topics

{{MCP Related}}