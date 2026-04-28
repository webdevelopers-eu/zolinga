---
name: system-authoring-manifest
description: Use when creating or editing module zolinga.json manifests, including listen/emit/autoload/config/webComponents/dependencies conventions and validation-oriented authoring.
argument-hint: "<module-name> [manifest-goal]"
---

# Zolinga Authoring Manifest

## Use When

- Creating a new `zolinga.json` for a module.
- Editing `listen`, `emit`, `autoload`, `config`, `webComponents`, or `dependencies`.
- Converting verbose declarations to supported sugar syntax (`service`, `request`).
- Verifying manifest correctness before implementing listeners/services.

## Manifest Structure

Top-level keys commonly used:

- `name`: human-readable module name.
- `version`: module version.
- `description`: human-readable summary.
- `authors`: author list.
- `attributes`: custom metadata.
- `listen`: event subscriptions.
- `emit`: informational list of emitted events for WIKI docs.
- `autoload`: PHP namespace/class mapping.
- `config`: module default config (merged into `$api->config`).
- `webComponents`: custom element registrations.
- `dependencies`: required modules (auto-installed).

## Authoring `listen`

Each listener declaration usually contains:

- `description`
- `event` or sugar (`service` / `request`)
- `class`
- `method` for non-service listeners
- `origin`
- optional `priority`
- optional `right`

Rules and behavior:

- `class` must implement `\Zolinga\System\Events\ListenerInterface`.
- `method` should be public and accept one event argument.
- `origin` values follow `OriginEnum`: `internal`, `remote`, `cli`, `custom`, `*`.
- Default priority is `0.5` when omitted.
- Higher priority executes earlier.

Sugar syntax:

- `"service": "myService"` means `"event": "system:service:myService"` and internal origin.
- `"request": "myRequest"` means `"event": "system:request:myRequest"` and remote origin.

## Authoring `emit`

- `emit` documents events your module may dispatch.
- Fields: `event`, `description`, `class`, `origin`.
- `method` and `priority` are not used in `emit`.
- Keep this section aligned with actual code behavior.

## Authoring `autoload`

- Map namespace prefixes to module-relative paths.
- Typical pattern: `"Vendor\\Module\\": "src/"`.
- Keep mapping consistent with actual file layout.
- Incorrect autoload causes runtime class resolution failures.

## Authoring `config`

- Provide module defaults only.
- Runtime values are merged with `config/global.json` and `config/local.json`.
- Access through `$api->config[...]`.

## Authoring `webComponents`

Each item includes:

- `tag`: custom element name.
- `description`: short explanation.
- `module`: path relative to module `install/dist/`.

## Authoring `dependencies`

- Declare every module your module relies on.
- Required when:
- your module consumes services from another module,
- your module expects listeners/providers from another module,
- your module requires data/features shipped by another module.
- Format supports module IDs with optional branch:
- `module-name`
- `module-name@branch`

## Workflow (Merged)

1. Update target module `zolinga.json` sections required by your change.
- `listen` for handlers/services.
- `emit` when code starts emitting events.
- `autoload` for namespace mapping.
- `dependencies` for cross-module requirements.
2. Validate behavior against merged cache in `data/system/system.cache.json`.
3. Bump module minor version when needed to force manifest cache refresh.
4. **Always update `CHANGELOG.md`** when bumping the version — see `system-changelog` skill.

## Practical Checklist

- Keep `listen` entries explicit and minimal.
- Use `service` / `request` sugar where it improves readability.
- Add `priority` only when ordering matters.
- Add `right` only for protected listeners.
- Keep `emit` in sync with actual emitted events.
- Keep `autoload` valid and up to date.
- Update `dependencies` whenever cross-module requirements appear.

## Starter Template

```json
{
  "name": "My Module",
  "version": "1.0",
  "description": "Short module description.",
  "authors": ["Dev <dev@example.com>"],
  "attributes": {},
  "listen": [
    {
      "service": "myService",
      "description": "My module service.",
      "class": "\\My\\Module\\MyService",
      "origin": ["internal"]
    },
    {
      "event": "my-module:run",
      "description": "Run module action.",
      "class": "\\My\\Module\\Api\\RunApi",
      "method": "onRun",
      "origin": ["remote"],
      "priority": 0.5
    }
  ],
  "emit": [
    {
      "event": "my-module:run",
      "description": "Run module action.",
      "class": "\\Zolinga\\System\\Events\\WebEvent",
      "origin": ["remote"]
    }
  ],
  "autoload": {
    "My\\Module\\": "src/"
  },
  "config": {
    "myModule": {
      "enabled": true
    }
  },
  "webComponents": [],
  "dependencies": []
}
```

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
- `system/src/Config/Atom/ListenAtom.php`
- `system/src/Config/ManifestService.php`
- `system/skeletons/module/zolinga.json`
