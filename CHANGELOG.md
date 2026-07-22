# Changelog

All notable changes to the Zolinga framework (system module) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0/).
## [1.6.19] - 2026-07-22

### Added
- **MCP `resources/list` and `resources/read` support.** The MCP gateway now implements the MCP Resources protocol. Module authors can drop `.meta.json` descriptor files (alongside content files) into `modules/<module>/mcp/resources/` to expose them as discoverable, readable resources. The `McpResourcesListHandler` auto-discovers them and rewrites internal `module://` URIs to the external `mcp-system:<module>:<basename>` scheme so internal paths are never leaked. The `McpResourcesReadHandler` serves text resources as `{ text }` and binary resources as `{ blob }` (base64-encoded) based on the `mimeType` in the `.meta.json`.
- **`McpEvent::validateResponse()`** — new hook method called by the gateway before producing any output to clients. The base implementation is a no-op; descendant event classes can override it to enforce response-level constraints (e.g. URI scheme whitelisting). `Resources\ListEvent` and `Resources\ReadEvent` override it to validate that all resource URIs use allowed schemes (`mcp-system`, `http`, `https`).
- **`Resources\ListEvent::addResourceJson()` and `addResource()`** — append resource descriptors to the `resources/list` response with validation (non-empty `uri` with allowed scheme, non-empty `name`).
- **`Resources\ListEvent::validateResponse()`** — validates all resource URIs in the response against the allowed scheme whitelist.
- **`Resources\ReadEvent::validateResponse()`** — validates the response content URIs against the allowed scheme whitelist.
- **`ResourcesEvent::ALLOWED_URI_SCHEMES`** constant and `isAllowedScheme()` method — defines the whitelist of URI schemes allowed in resource responses (`mcp-system`, `http`, `https`).
- **`resources` capability** added to the `initialize` response. The `instructions` text now mentions `resources/list` and `resources/read`.

### Changed
- **`Resources\ReadEvent` constructor** now extracts the URI scheme from the `uri` request parameter and appends it to the event type (e.g. `mcp:resources/read:mcp-system`), enabling per-scheme handler registration.
- **`McpServer::buildResponse()`** now calls `$event->validateResponse()` before producing output. If validation throws, the event status is set to `ERROR` and an error response is returned.

## [1.6.18] - 2026-07-21

### Changed
- **`McpTools` renamed to `McpToolsListHandler`.** The `tools/list` handler class is now `Zolinga\System\Mcp\McpToolsListHandler` (file `src/Mcp/mcp-tools-list-handler.php`), matching the `*Handler` suffix convention used by `McpInitializeHandler` and `McpEchoHandler`. The system manifest `mcp:tools/list` listener class reference was updated. All wiki and skill documentation references updated.

## [1.6.17] - 2026-07-21

### Added
- **`Mcp\Tools\ListEvent::addTool()`** — appends a tool to the `tools/list` response with validation. Checks the tool name against the MCP character class (`[A-Za-z0-9_:-]{1,64}`, no `mcp:` prefix) via `McpHelper::isValidToolName()` and rejects schemas that are not JSON objects (associative arrays), throwing `InvalidArgumentException` on any violation. Lets custom `tools/list` handlers build the catalogue programmatically without bypassing the wire contract.

### Changed
- **`McpTools::collectTools()`** now populates the response via `ListEvent::addTool()` instead of building the tool array directly. The name-format and schema-shape validation that `addTool()` provides now guards every tool the manifest walk emits; a tool that fails validation is logged and skipped rather than poisoning the response. Behavior (tool set, ordering, schemas) is unchanged.

## [1.6.16] - 2026-07-21

### Changed
- **MCP event class hierarchy introduced.** The single `\Zolinga\System\Events\McpEvent` class is now an abstract base under the new `\Zolinga\System\Events\Mcp` namespace, with one concrete subclass per JSON-RPC method: `InitializeEvent` (`initialize`), `Tools\ListEvent` (`tools/list`), `Tools\CallEvent` (`tools/call`), `Prompts\ListEvent`/`Prompts\GetEvent` (`prompts/*`), and `Resources\ListEvent`/`Resources\ReadEvent` (`resources/*`). `McpEvent::fromJsonRpc()` resolves the correct subclass via a `match` on the JSON-RPC `method`. The system manifest now type-hints `mcp:initialize` and `mcp:tools/list` listeners with their concrete event classes (`InitializeEvent`, `Tools\ListEvent`) instead of the generic `McpEvent`.

### Removed
- **Legacy `\Zolinga\System\Events\McpEvent`** (the old flat class at `src/Events/McpEvent.php`) — replaced by the `Mcp` namespace hierarchy. Tool handlers now type-hint `Tools\CallEvent` instead of `McpEvent`.
- **`McpEvent::$isToolCall` flag.** The gateway now distinguishes `tools/call` invocations by `instanceof CallEvent` (and wraps the response in the MCP envelope accordingly) instead of a boolean flag set by `fromJsonRpc()`.
- **`McpRequestValidator`** class — its validation logic was already moved into `McpEvent::fromJsonRpc()` in 1.6.8; the leftover dead file (`src/Mcp/McpRequestValidator.php`) is now deleted.

## [1.6.15] - 2026-07-20

### Changed
- **MCP tool names now allow `:` in the character set.** `McpHelper::TOOL_NAME_CHAR_CLASS` changed from `[A-Za-z0-9_-]` to `[A-Za-z0-9_:-]` so that Zolinga event names with colons (e.g. `my-module:search`) can be exposed as MCP tools verbatim. `McpHelper::isValidToolName()` now explicitly rejects names starting with `mcp:` to prevent collision with protocol events, replacing the previous implicit guarantee that relied on `:` being absent from the allowed charset.

## [1.6.14] - 2026-07-20

### Changed
- **MCP protocol events are now prefixed with `mcp:`.** All non-`tools/call` JSON-RPC methods dispatched by the MCP gateway are prefixed with `mcp:` and keep their original method path verbatim (e.g. `initialize` → `mcp:initialize`, `tools/list` → `mcp:tools/list`, `notifications/initialized` → `mcp:notifications/initialized`). `tools/call` still uses the bare tool name as the event type. `McpTools::isReservedEvent()` excludes protocol events with a single `str_starts_with($eventName, 'mcp:')` check instead of a hardcoded list — new protocol methods are automatically excluded.

## [1.6.13] - 2026-07-20

### Changed
- **MCP tools no longer use the `tools:call:` event-name prefix.** MCP tool events are now distinguished from other MCP events solely by the `mcp` origin and the presence of a `schema.response`, not by an event-name prefix. The gateway dispatches `tools/call` using the bare tool name (`params.name`) as the event type (e.g. `echo` instead of `tools:call:echo`). `McpEvent` gained an `isToolCall` flag (set by `fromJsonRpc()`) that the gateway uses to decide envelope wrapping and `isError` mapping, replacing the previous `str_starts_with($event->type, 'tools:call:')` check. `McpTools::collectTools()` now uses the listener's event name verbatim as the tool name and excludes reserved MCP protocol events (`initialize`, `tools:list`, `notifications:*`) instead of matching the `tools:call:` prefix. The `tools:call:*` emit entry was removed from the system manifest.
## [1.6.12] - 2026-07-15

### Fixed
- `WWW-Authenticate` header now quotes the `resource_metadata` URL value per RFC 9728 Section 5.1 (e.g. `Bearer resource_metadata="https://..."`). Some clients failed to parse the unquoted form correctly.

## [1.6.10] - 2026-07-14

### Changed
- `McpServer::sendHeadersForStatus()` now uses `$api->url->resolveUrl()` to build the `WWW-Authenticate` header URL instead of manually concatenating `baseURL` + path. This ensures the PRM URL is resolved consistently with the rest of the system.

## [1.6.8] - 2026-06-27

### Changed

- **MCP gateway rewritten from scratch**: `McpServer` reduced from ~640 to ~250 lines. Clean linear pipeline: parse JSON -> create `McpEvent` -> dispatch -> serialize response. Dropped batch support (MCP Streamable HTTP spec is one message per request). Dropped `firstMethod`/`firstToolName` state fields, `process()`, `isBatch`, and 5 separate send methods.
- **`McpEvent::fromJsonRpc()`**: New static factory on `McpEvent` that validates the JSON-RPC 2.0 envelope and resolves the Zolinga event type (`tools/call` -> `tools:call:<name>`, otherwise `/` -> `:`). Moves input parsing into the event class where it belongs.
- **`McpServer` API change**: `run()` is the single public entry point (replaces `process()->send()`). `sendException()` renamed to `sendError()`. HTTP response status now reflects the event status (401 for UNAUTHORIZED, 404 for NOT_FOUND, etc.) instead of always 200. UNAUTHORIZED responses include a `WWW-Authenticate: Bearer resource_metadata=...` header pointing to the OAuth Protected Resource Metadata endpoint (RFC 9728).
- **MCP gateway refactoring**: `McpToolsCallEvent` merged into `McpEvent`. The gateway now uses `str_starts_with($event->type, 'tools:call:')` instead of `instanceof` to decide envelope wrapping. Tool handlers type-hint `McpEvent` instead of `McpToolsCallEvent`.
- **Removed `McpToolsCallEvent`**: The `$content` property and `addTextContent()` method are gone. The gateway always auto-generates `content` from `json_encode($response)` (or `$event->message` on error). No tool in the codebase used them.
- **`AuthorizeEvent`**: Added `requiresLogin` property (bool) and `requireLogin()` setter. When `true`, a failed authorization should result in HTTP 401 Unauthorized; when `false` (default), HTTP 403 Forbidden.

### Removed

- `McpRequestValidator` class — logic moved into `McpEvent::fromJsonRpc()`.
- `McpHelper::BATCH_MAX_REQUESTS` constant — batches no longer supported.
- `McpToolsCallEvent` class — use `McpEvent` instead.
- `McpEvent::$content` property and `addTextContent()` method — the gateway auto-generates content blocks.

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