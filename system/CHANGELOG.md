# Changelog

All notable changes to the Zolinga System module are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.7.1] - 2026-09-01

### Added
- MCP `notifications/initialized` notification accepted as a no-op. The gateway now replies with HTTP 204 No Content for JSON-RPC notifications (requests without an `id`), preventing clients that send notifications from failing on an unknown-method error.

## [1.7.0] - 2026-09-01

### Removed
- MCP tenant support completely removed. The gateway now supports only `/mcp` and `/mcp/oauth` endpoints. The `/mcp/oauth/{tenant}` route, `@{tenant}` event suffixing, tenant inheritance loop, `McpTenantException`, `McpHandler::isMatchingTenant()`/`assertTenant()`, and the `tenants` field in prompt/resource `.meta.json` are all removed. All tenant references in skill files and wiki docs are cleaned up.

## [1.6.29] - 2026-08-08

### Added
- `system-action-plan` skill for creating actionable, trackable review/implementation plan documents in `/TODO/`.

## [1.6.22] - 2026-07-27

### Added
- `mcp-redirect` web component. The browser-facing `/mcp/` redirect page now loads a `<mcp-redirect>` custom tag whose HTML/CSS live in the component. Text content is translatable via gettext (domain `system`).

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
