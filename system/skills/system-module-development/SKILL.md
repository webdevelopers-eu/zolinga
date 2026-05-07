---
name: system-module-development
description: Use when creating or updating Zolinga modules, wiring services/events in zolinga.json, handling config access, and following core project structure conventions.
argument-hint: "<module-name> [goal]"
---

# Zolinga Module Development

## Use When

- Creating a new module or changing an existing module's architecture.
- Registering listeners, services, and emitted events in `zolinga.json`.
- Accessing global services and configuration in PHP code.

## Workflow

1. Keep module code in `modules/<module-name>/` and core code in `system/`.
2. Put PHP source files in `src/` under the target module.
3. Access global services via `global $api;` and `$api->serviceName`. Do not use dependency injection — just `global $api`.
4. List of all services is in `data/system/api.stub.php`.
5. Add or update listeners/services in module `zolinga.json`.
6. When adding or firing events, update the `emits` section in `zolinga.json`.
7. If `zolinga.json` changes, bump minor module version to force cache reload, then run `bin/zolinga` (no parameters) to trigger manifest rescan and install/update pipeline.
8. Use Zolinga path conventions (`config://`, module-relative paths) when reading config files.
9. Logs are recorded via `$api->log` and stored in `data/system/logs/messages.log`.
10. **Temporary scripts** (ad-hoc test/debug) go in `./tmp/ai-*.*` with the `ai-` prefix. For other folders: `./data/system/tmp/`, `./public/data/system/tmp/`, `./public/dist/system/tmp/`, or `./public/tmp/` (for public URL access). Create `tmp/` dirs if needed. Clean up after use.

## Runtime Constants (defined in `system/define.php`)

| Constant | Value | Description |
|----------|-------|-------------|
| `Zolinga\System\ROOT_DIR` | `dirname(__DIR__, 1)` | Absolute filesystem path to the project root (parent of `system/`). Use to build absolute paths to any file in the project without hardcoding. |

## Quick Checks

- Verify namespace and autoload mapping in module `zolinga.json`.
- All autoload mappings are merged into `data/system/system.cache.json` (`autoload` section).
- Verify merged config is accessible via `$api->config[...]`.
- Verify references against `data/system/system.cache.json` when debugging wiring.

## Documentation Abstract

- Start with architecture and module shape: `Zolinga Core.md`, then `Module Anatomy.md`.
- For registration and wiring, move to `Manifest File.md` and `Events and Listeners.md`.
- For runtime-facing details, use `Services.md`, `Configuration.md`, and `Paths and Zolinga URI.md`.
- To ship Agent Skills with a module, place them in `modules/<module>/skills/<skill-name>/SKILL.md`; the installer auto-symlinks them into `.agents/skills/{module}-{skill-name}` on every install/update run.

## References

- `system/wiki/Zolinga Core.md`
- `system/wiki/Zolinga Core/Module Anatomy.md`
- `system/wiki/Zolinga Core/Events and Listeners.md`
- `system/wiki/Zolinga Core/Manifest File.md`
- `system/wiki/Zolinga Core/Services.md`
- `system/wiki/Zolinga Core/Configuration.md`
- `system/wiki/Zolinga Core/Paths and Zolinga URI.md`
- `system/wiki/ref/event/skeleton/module.md`
