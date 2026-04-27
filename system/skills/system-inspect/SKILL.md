---
name: system-inspect
description: How to query the Zolinga system's runtime state — merged manifest cache, API service stubs, configuration layers, and log files — using jq, grep, tail, and PHP.
argument-hint: "<query-type: cache|api|config|log> [details]"
---

# System Inspect — Querying Zolinga Runtime State

## Use When

- You need to see which events, listeners, services, or autoload entries are registered across all modules.
- You need to check what services are available on `$api` and their types.
- You need to inspect merged configuration or find where a config key is set.
- You need to read logs to debug a request, cron run, or error.

## Key Files

| What | Path | Notes |
|------|------|-------|
| Merged manifest (all `zolinga.json`) | `data/system/system.cache.json` | Top-level keys: `signatures`, `manifests`, `autoload`, `listen`. Best queried with `jq` or PHP's `json_decode()` |
| API service stubs | `data/system/api.stub.php` | `$api->*` services with class types and doc comments |
| Merged config | `data/system/config.cache.json` | May contain secrets (DB passwords, API keys). `#`-prefixed keys are origin markers |
| Global config | `config/global.json` | Shared across environments |
| Local config | `config/local.json` | Environment-specific, overrides global |
| Log file | `data/system/logs/messages.log` | Can be gigabytes — always use `tail` |

## Querying the Merged Manifest

The file `data/system/system.cache.json` is a single JSON object merging all modules' `zolinga.json` files. Top-level keys: `signatures`, `manifests`, `autoload`, `listen`.

**Note:** The cache contains *normalized* data. Sugar syntax from `zolinga.json` is expanded:
- `"service": "log"` → `"event": "system:service:log"` (the `service` key is replaced by the full `event` key)
- `"request": "name"` → `"event": "system:request:name"` (same pattern)
- Other sugar shortcuts are resolved to their canonical `event` form

So when querying the cache, always filter by `event` — the original sugar keys no longer exist.

### Listener Object Format

Every entry in the `listen` array is normalized to the same 7 fields — no partial entries, no missing keys:

```json
{
  "event": "system:content",
  "class": "\\Zolinga\\System\\Cms\\Page",
  "method": "onContent",
  "origin": ["remote"],
  "description": "Super low priority default page handler that displays a placeholder page if event has not status set/is not handled already.",
  "priority": 0.001,
  "right": false
}
```

| Field | Values | Description |
|-------|--------|-------------|
| `event` | string | Full event name (sugar syntax already expanded) |
| `class` | string | Fully qualified class name with leading `\` |
| `method` | string \| null | Method name on the class, or `null` (class itself is invoked) |
| `origin` | string[] | Array of allowed origins, e.g. `["remote"]`, `["cli"]`, `["remote","internal"]` |
| `description` | string | Human-readable description |
| `priority` | float | Execution priority, default `0.5`; lower = runs later |
| `right` | false \| string | `false` = no auth required, or a right string like `"member of administrators"` |

**Note:** This file is automatically regenerated whenever any `zolinga.json` changes (detected via checksum mismatch, e.g. version bump). No manual refresh needed.

```bash
# List all registered event listeners (array of {event, class, method, origin, ...})
jq '.listen' data/system/system.cache.json

# List all unique event names
jq '[.listen[] | .event] | unique' data/system/system.cache.json

# Find listeners for a specific event (e.g. cms:content:ai-text)
jq '[.listen[] | select(.event == "cms:content:ai-text")]' data/system/system.cache.json

# Find all services (sugar "service": "name" in zolinga.json becomes "event": "system:service:name")
jq '[.listen[] | select(.event | startswith("system:service:")) | {service: (.event | sub("system:service:"; "")), class: .class}]' data/system/system.cache.json

# Find autoload mappings (object: namespace → path)
jq '.autoload' data/system/system.cache.json

# Find a specific namespace in autoload
jq '.autoload["Zolinga\\Cms\\"]' data/system/system.cache.json

# List all module manifest files
jq '.manifests' data/system/system.cache.json

# Count listeners per event
jq '[.listen[] | .event] | group_by(.) | map({event: .[0], count: length})' data/system/system.cache.json
```

In PHP:
```php
$cache = json_decode(file_get_contents('data/system/system.cache.json'), true);
$listeners = array_filter($cache['listen'], fn($l) => $l['event'] === 'cms:content:my-tag');
```

## Inspecting API Services

The file `data/system/api.stub.php` defines the `ApiStub` class with `@property` annotations for every service on `$api`.

```bash
# List all available services
grep '@property' data/system/api.stub.php

# Find a specific service and its type
grep 'db' data/system/api.stub.php
```

Example output:
```php
/** @var Zolinga\Db\Service\DbService $db */
```

## Inspecting Configuration

Three layers, merged at runtime (later overrides earlier):

1. `config/global.json` — shared defaults
2. `config/local.json` — environment overrides
3. Module config files in `config/{module-name}/` — accessed via `config://{module-name}/` URI

The merged result is cached in `data/system/config.cache.json`. Keys prefixed with `#` are comment/origin markers (e.g. `"# database"` indicates where the `database` key came from):

```bash
# List all top-level config keys
jq 'keys' data/system/config.cache.json

# View full merged config (may contain secrets!)
jq '.' data/system/config.cache.json

# Drill into a specific key
jq '.database' data/system/config.cache.json

# Find where a key originates (check the # comment key)
jq '."# database"' data/system/config.cache.json

# Compare with source files
jq '.' config/global.json
jq '.' config/local.json
```

See `system/wiki/Zolinga Core/Config Event.md` for the config event lifecycle.

## Reading Logs

The log file `data/system/logs/messages.log` can grow to gigabytes. **Never `cat` or `less` the whole file.**

### Log Format

```
[2026-01-12T11:15:01+00:00] ::1 [cli:info] 4fgg/104607 1.0M 🔵 "🟣 Starting Zolinga CLI script: bin/zolinga gtm:inbox"
```

Format: `[{date}] {client} [{category}:{severity}] {logId}/{pid} {memory} {emoji} "{message}" """" {context}`

| Field | Description |
|-------|-------------|
| `[timestamp]` | ISO 8601 with timezone |
| `client` | `$_SERVER['REMOTE_ADDR']` for web requests, or `php_sapi_name()` for CLI (usually `cli`) |
| `[category:severity]` | First param to `$api->log->*()` — module-dot-separated category, colon, then severity (`info`, `warning`, `error`) |
| `logId/pid` | Short random run ID (4 chars) / process ID — grep by run ID to trace a single request |
| `memory` | Current memory usage (e.g. `1.0M`) |
| `emoji` | 🔵 = info, 🟠 = warning, 🔴 = error |
| `"message"` | The logged string (JSON-encoded) |
| `"""" {context}` | Optional — extra JSON context array passed as 3rd argument |

### Useful Commands

```bash
# Tail recent entries
tail -n 50 data/system/logs/messages.log

# Follow live
tail -f data/system/logs/messages.log

# Search for a specific run ID
grep '4fgg' data/system/logs/messages.log

# Filter by severity
grep '\[:error\]' data/system/logs/messages.log
grep '\[:warning\]' data/system/logs/messages.log

# Filter by category prefix (e.g. all ipd:xxx categories)
grep '\[ipd\.' data/system/logs/messages.log

# Filter by category+severity (e.g. cron errors)
grep '\[cron:error\]' data/system/logs/messages.log

# Count errors per day
grep -c '\[:error\]' data/system/logs/messages.log

# Show only errors with context
grep -B 2 '\[:error\]' data/system/logs/messages.log | tail -100

# Check log size
ls -lh data/system/logs/messages.log
```

## References

- `system/wiki/Zolinga Core/Config Event.md`
- `system/wiki/Zolinga Core/Manifest File.md`
- `data/system/api.stub.php`
- `data/system/system.cache.json`
- `data/system/config.cache.json`
