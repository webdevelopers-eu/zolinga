# Changelog

All notable changes to the Zolinga framework (system module) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0/).

## [1.6.6] - 2026-06-25

### Changed

- **Content event refactoring**: `ContentEvent` is now an abstract base class with concrete subclasses for HTML, JSON, and plain text content.
- **Two-phase content dispatch**: `PreflightEvent` determines MIME type before the content event, enabling non-HTML responses (JSON, text) from any URL.
- **New `ContentMimeTypesEnum`** for mapping MIME types to content event classes.
- MCP server logging improvements.
- Updated all documentation and skills.

## [1.6.3] - 2026-06-03

### Added
- Per-request access log on the MCP gateway (`/mcp/`). Every request — POST, GET, DELETE, etc. — produces a short `system:mcp:info` line via `$api->log` with the request contents (method, tool, size, batch flag, response status). No IP or User-Agent duplication (Apache already records those).
- `McpServer::run()` — top-level request entry point that picks POST vs non-POST and returns a spec-compliant 405 with `Allow: GET, POST, DELETE` for non-POST (per MCP Streamable HTTP spec §2.2 / §2.5).
- `McpServer::logAccess(int $status, ?string $method, bool $isBatch, ?string $toolName)` — the helper behind the access log. Bounded via `McpHelper::truncateForEcho()`.

## [1.6.2] - 2026-06-03

### Security
MCP gateway hardening after a soft penetration test. No protocol or API changes for well-behaved clients.

- 64 KiB request body cap (HTTP 413).
- 50-request batch cap.
- 64-char `id` cap, 256-char `method` cap — oversize values are not echoed back.
- `McpHelper::truncateForEcho()` — strips control characters, caps at 200 chars, applied to every attacker-controlled field in error responses and log lines.
- Top-level scalar JSON is now a parse error (was silent 204).
- Empty batches are an invalid-request error (was silent 204).
- Invalid `Mcp-Session-Id` values are dropped with a truncated warning.
- Non-POST requests are rejected with HTTP 405 (POST is the only method that carries a JSON-RPC message; the spec allows 405 for non-streaming gateways).

## [1.6.1] - 2026-06-03

### Added
- `McpToolsCallEvent` (extends `McpEvent`) — per-tool event dispatched as `tools:call:<name>`. Carries a `$content` array of human-readable blocks and an `addTextContent()` helper. Handlers set the raw structured payload on `$event->response`; the gateway builds the MCP `{ content, isError, structuredContent }` envelope.
- `McpHelper::envelope(McpToolsCallEvent)` — builds the standard MCP `tools/call` result envelope. When the handler adds no content blocks, the gateway falls back to a single text block carrying `json_encode($event->response)`.
- `McpTools` enforces `schema.response` on every exposed tool. Listeners without one are skipped by `tools/list` and an error is logged via `$api->log->error()`.

### Changed
- The gateway now wraps `tools/call` responses — handlers no longer build the envelope. Errors surface as `result.isError = true`, never as a JSON-RPC `error` block.
- Unknown tools are an MCP error envelope, not a JSON-RPC `error` block.

## [1.5.1] - 2026-06-03

### Added
- MCP-conformant gateway: separate handlers for `initialize` (lifecycle payload only), `tools/list` (tool discovery) and `tools/call` (tool invocation).
- New `McpTools` class, `McpServer` orchestrator, `McpRequestValidator`, `McpHelper`, and exception classes (`McpException`, `McpParseErrorException`, `McpInvalidRequestException`, `McpMethodNotFoundException`, `McpInvalidParamsException`, `McpInternalErrorException`).
- New `McpEchoHandler` example tool.
- `tools/list` now excludes reserved MCP protocol methods.
- WIKI: new [`tools/list`](:ref:event:tools/list) and [`tools/call`](:ref:event:tools/call) reference pages; [`MCP` page](:Zolinga Core:Running the System:MCP) rewritten.

## [1.4.1] - 2026-06-03

### Added
- MCP (Model Context Protocol) non-streaming HTTP gateway at `public/mcp/index.php`. Translates JSON-RPC 2.0 requests into Zolinga events dispatched from the new `mcp` origin.
- New event class `\Zolinga\System\Events\McpEvent` (extends `RequestResponseEvent`, adds the JSON-RPC `id`).
- New system handler `\Zolinga\System\Mcp\McpInitializeHandler` that handles the MCP `initialize` request and returns the list of all listeners that opt in to the `mcp` origin, including descriptions and JSON Schema files.
- New `mcp` value in `\Zolinga\System\Types\OriginEnum` and a matching `Event::ORIGIN_MCP` constant.
- Optional `schema` property on listen atoms (`{ request?: string, response?: string }`) — each value is a Zolinga URI pointing to a JSON Schema file.
- Documentation: [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP), [MCP Events](:Zolinga Core:Events and Listeners:MCP), and event reference pages for `initialize` and `mcp:echo`.

## [1.3.8] - 2026-04-29

## [1.4.1] - 2026-06-03

### Added
- MCP (Model Context Protocol) non-streaming HTTP gateway at `public/mcp/index.php`. Translates JSON-RPC 2.0 requests into Zolinga events dispatched from the new `mcp` origin, and serializes responses back as JSON-RPC 2.0 messages.
- New event class `\Zolinga\System\Events\McpEvent` (extends `RequestResponseEvent`, adds the JSON-RPC `id`).
- New system handler `\Zolinga\System\Mcp\McpInitializeHandler` that handles the MCP `initialize` request and returns the list of all listeners that opt in to the `mcp` origin, including descriptions and JSON Schema files.
- New `mcp` value in `\Zolinga\System\Types\OriginEnum` and a matching `Event::ORIGIN_MCP` constant.
- Optional `schema` property on listen atoms (`{ request?: string, response?: string }`) — each value is a Zolinga URI pointing to a JSON Schema file (e.g. `module://my-module/schema/mcp/tool.json`). The MCP `initialize` handler embeds the parsed schema as `inputSchema` / `outputSchema`.
- Documentation: [MCP (Model Context Protocol)](:Zolinga Core:Running the System:MCP), [MCP Events](:Zolinga Core:Events and Listeners:MCP), and event reference pages for `initialize` and `mcp:echo`.

## [1.3.8] - 2026-04-29

### Added
- `system-changelog` skill: guidance for creating and maintaining CHANGELOG.md files per Keep a Changelog and SemVer standards.
- Manifest skill now requires updating CHANGELOG.md when bumping module version.

## [1.3.7] - 2026-04-28