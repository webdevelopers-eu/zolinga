# Changelog

All notable changes to the Zolinga System module are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.21] - 2026-07-22

### Added
- Global `mcp.enabled` config switch. When set to `false`, the MCP gateway returns HTTP 404 with a JSON-RPC error instead of processing requests.

## [1.6.20] - 2026-07-22

### Added
- MCP `prompts/list` and `prompts/get` support. Prompts are defined as `.meta.json` files in `modules/<module>/mcp/prompts/`. The filename is the identifier, rewritten to `mcp-system:<module>:<basename>`. Supports inline text, file references via `content.uri`, multi-message conversations, and `{{arg}}` placeholder substitution.
- `prompts` capability declared in `initialize` response.
- `McpPromptsListHandler` and `McpPromptsGetHandler` classes.
- `PromptsEvent`, `ListEvent`, `GetEvent` event classes with `ALLOWED_URI_SCHEMES = ['mcp-*']`.

### Fixed
- `content.uri` field in prompt messages is now always stripped from the response, even when `content.text` is also present. Previously the internal `module://` or `file://` URI would leak to the client.
- Path traversal containment check now uses trailing `DIRECTORY_SEPARATOR` to prevent directory prefix collision (e.g. `ipdefender` vs `ipdefender-evil`).
- `GetEvent` and `ReadEvent` constructors now throw `McpInvalidRequestException` instead of `InvalidArgumentException` for disallowed URI schemes. This produces a proper JSON-RPC error response instead of an HTTP 500 with empty body.
