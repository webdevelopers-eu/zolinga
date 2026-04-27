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
| Merged manifest (all `zolinga.json`) | `data/system/system.cache.json` | ⚠️ Auto-generated — do not edit. Regenerated from all `zolinga.json` files on change. |
| API service stubs | `data/system/api.stub.php` | ⚠️ Auto-generated — do not edit. Regenerated from service registrations. |
| Web components registry | `public/data/system/web-components.json` | ⚠️ Auto-generated — do not edit. Regenerated from `webComponents` sections in `zolinga.json`. |
| Merged config | `data/system/config.cache.json` | ⚠️ Auto-generated — do not edit. Merged from `global.json` + `local.json` + module configs. |
| Global config | `config/global.json` | ✏️ Editable — shared defaults across environments. |
| Local config | `config/local.json` | ✏️ Editable — environment-specific overrides. |
| Log file | `data/system/logs/messages.log` | ⚠️ Auto-generated — do not edit. Can be gigabytes — always use `tail`. |

> **Rule of thumb:** Only `config/global.json` and `config/local.json` are meant to be directly edited. All other files listed above are auto-generated from module manifests and runtime state — editing them will have no lasting effect (they get overwritten on the next cache rebuild). To change services, events, autoload mappings, or web components, edit the relevant module's `zolinga.json` instead.

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

## Class-to-File Mapping (Autoload)

The `autoload` section in `data/system/system.cache.json` maps PSR-4 namespace prefixes to source directories. Each module's `zolinga.json` declares its autoload rules, and they are merged into this single object.

```bash
# View all autoload mappings
jq '.autoload' data/system/system.cache.json

# Find which directory a namespace maps to
jq '.autoload["Zolinga\\Cms\\"]' data/system/system.cache.json
```

Example output:
```json
{
  "Zolinga\\Cms\\": "modules/zolinga-cms/src/",
  "Zolinga\\System\\": "system/src/",
  "Ipd\\Base\\": "modules/ipdefender-base/src/"
}
```

### Resolving a Class to Its File

To find the PHP file for any class, match the class's namespace against the autoload prefix, then append the relative class path:

1. Take the fully qualified class name, e.g. `Zolinga\Cms\Abc\Def`
2. Find the longest matching autoload prefix: `Zolinga\Cms\` → `modules/zolinga-cms/src/`
3. Strip the prefix from the class name: `Abc\Def`
4. Replace `\` with `/` and append `.php`: `Abc/Def.php`
5. Prepend the directory: `modules/zolinga-cms/src/Abc/Def.php`

```bash
# Quick one-liner: given class Zolinga\Cms\Abc\Def, find the file
# 1. Get the base path for the namespace
jq -r '.autoload["Zolinga\\Cms\\"]' data/system/system.cache.json
# → "modules/zolinga-cms/src/"
# 2. Build the full path
echo "modules/zolinga-cms/src/Abc/Def.php"
```

**Important:** PHP class filenames use PascalCase matching the class name (e.g. `Def.php`), **not** kebab-case. This is the only exception to the kebab-case filename convention in Zolinga.

## Inspecting API Services

The file `data/system/api.stub.php` defines the `ApiStub` class with `@property` annotations for every service on `$api`. This is meant primarily for editors to provide autocomplete and type hints, but it can also be grepped to see what services are available and their types.

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

## Inspecting Web Components

The file `public/data/system/web-components.json` lists all registered custom HTML tags and the JS modules that implement them. Each entry:

```json
{
  "tag": "call-to-action",
  "module": "/dist/ipdefender/web-components/call-to-action/call-to-action.js",
  "priority": 0.5,
  "description": "Call-to-action button that opens the alert editor with optional pre-filled brand name."
}
```

| Field | Description |
|-------|-------------|
| `tag` | Custom element tag name (used as `<call-to-action>` in HTML) |
| `module` | JS module path relative to `public/` — the system auto-registers via `customElements.define()` |
| `priority` | Execution priority (default `0.5`) |
| `description` | Human-readable description |

```bash
# List all registered web components
jq '.' public/data/system/web-components.json

# List just the tag names
jq '[.[] | .tag]' public/data/system/web-components.json

# Find a specific component by tag
jq '[.[] | select(.tag == "call-to-action")]' public/data/system/web-components.json

# Find all components from a specific module
jq '[.[] | select(.module | startswith("/dist/ipdefender/"))]' public/data/system/web-components.json
```

See `system/wiki/Zolinga Core/Web Components.md` for the full web components documentation.

## References

- `system/wiki/Zolinga Core/Config Event.md`
- `system/wiki/Zolinga Core/Manifest File.md`
- `data/system/api.stub.php`
- `data/system/system.cache.json`
- `data/system/config.cache.json`
