---
name: system-manifest-events
description: Use when modifying module zolinga.json manifest data, especially listen, service, emits, autoload mappings, and cache refresh behavior.
argument-hint: "<module-name> [manifest-change]"
---

# Zolinga Manifest and Events

## Use When

- Updating module `zolinga.json`.
- Registering listeners, services, or autoload mappings.
- Adding emitted events.

## Workflow

1. Update target module `zolinga.json` sections:
- `listen` for event handlers.
- `service` for service registrations.
- `emits` when code starts firing new events.
- `autoload` for class/file mapping.
2. Validate behavior against merged cache in `data/system/system.cache.json`.
3. Bump module minor version when needed to force manifest cache refresh.

## Documentation Abstract

- Start with `Manifest File.md` for schema and semantics of `zolinga.json`.
- Use `Events and Listeners.md` for listener contracts and event flow.
- Use `ref/event/system/service/manifest.md` when debugging manifest resolution internals.

## References

- `system/wiki/Zolinga Core/Manifest File.md`
- `system/wiki/Zolinga Core/Events and Listeners.md`
- `system/wiki/Zolinga Core/Events and Listeners/Event Authorization.md`
- `system/wiki/Zolinga Core/Events and Listeners/Remote Events.md`
- `system/wiki/ref/event/system/service/manifest.md`
