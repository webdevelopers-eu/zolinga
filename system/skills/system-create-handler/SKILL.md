---
name: system-create-handler
description: Use when creating a new event handler/listener in a Zolinga module, including event naming, class placement, origin filtering, rights checks, and event object selection.
argument-hint: "<module-name> <event-or-request-or-tag> [goal]"
---

# Zolinga Create Handler

## Use When

- Adding a new listener entry to `listen` in a module `zolinga.json`.
- Implementing handlers for custom events, CLI events, page requests, AJAX, or CMS server-rendered tags.
- Choosing event names, class names, method names, priority, origin, and authorization (`right`).

## Core Contracts

- Listener classes must implement `\Zolinga\System\Events\ListenerInterface`.
- Handler methods are public and should accept exactly one typed event argument.
- For non-service listeners, `method` is required in `zolinga.json` (runtime throws if missing).
- `origin` filters listener activation. Event names match with `*` wildcards.

## Handlers vs Services

- Event handlers are loosely coupled integration points: an emitted event may have zero listeners and the emitter can still continue.
- Services are direct module APIs and are typically more convenient/faster for frequent cross-module calls.
- If handler logic optionally uses a service that may not be installed, guard with `$api->serviceExists(string $name)`.

## Event Naming Conventions (Observed)

- Prefix by module/domain: `ipd:*`, `rms:*`, `wiki:*`, `cron:*`, `cms:content:*`, `system:*`.
- Use lowercase and `:`-separated segments.
- Keep names short and operation-oriented.
- Examples: `rms:login`, `ipd:dashboard`, `system:install:script:sql`, `cms:content:replace-vars`.
- For CMS server-rendered tags, use `cms:content:<tag-name>` where `<tag-name>` equals the HTML custom element local name.

## Class Naming and Placement (Observed Patterns)

- Common class suffixes: `*Listener`, `*Api`, `*Cli`.
- Common folders: `src/Api/`, `src/Content/` or `src/Listeners/`, `src/Cli/`, or module `src/` root.
- Follow module-local style. In this repository, classes typically use PSR-4 paths and class-like file names.

## Choose Correct Event Object Type

Origin does not force event class by itself. Event class is decided by the emitter:

- `bin/zolinga EVENT ...` dispatches `\Zolinga\System\Events\CliRequestResponseEvent` (origin `cli`) with `request` and `response`.
- `public/index.php` dispatches:
- `system:request:<key>` as `\Zolinga\System\Events\RequestEvent` (origin `remote`).
- `system:content` as `\Zolinga\System\Events\ContentEvent` (origin `remote`).
- `system/install/dist/gate/index.php` dispatches `\Zolinga\System\Events\WebEvent` (origin `remote`) for AJAX/gate calls.
- CMS parser dispatches `cms:content:<tag>` as `\Zolinga\Cms\Events\ContentElementEvent` (origin `internal`).
- Service discovery emits `system:service:<name>` as base `\Zolinga\System\Events\Event` (origin `internal`).

## Origin Behavior (OriginEnum)

Supported origin values:

- `internal`: trusted in-process events.
- `remote`: HTTP/AJAX/request-originated events.
- `cli`: command-line triggered events.
- `custom`: reserved for custom third-party channels.
- `*`: wildcard in listener subscriptions.

Matching notes:

- Listener runs when event origin is in its `origin` list or listener contains `*`.
- Event-side special `OriginEnum::ANY` exists mainly for discovery/introspection queries.

## `zolinga.json` Preferred Syntax

Use concise sugar when possible:

- `"service": "name"` instead of `"event": "system:service:name"`.
- `"request": "name"` instead of `"event": "system:request:name"`.

Syntactic effects:

- `service` sugar auto-targets `system:service:<name>` and normalizes to `origin: ["internal"]`.
- `request` sugar auto-targets `system:request:<name>` and normalizes to `origin: ["remote"]`.

## Priorities

- Default priority is `0.5`.
- Higher runs earlier; lower runs later.
- Set priority only when order matters.
- Typical intentional overrides observed: `0.8-0.9` (pre-processing), `0.1` (post-processing), `0.001` (fallback).

## Authorization (`right`) and RMS Integration

- Listener-level access control uses `right` (singular) on `listen` entries.
- Before invoking a protected listener, core dispatches internal `system:authorize` as `\Zolinga\System\Events\AuthorizeEvent`.
- Authorization providers inspect `$event->unauthorized` and call `$event->authorize(...)` for rights they can confirm.
- With `zolinga-rms` installed, `\Zolinga\Rms\UserService::onAuthorize()` acts as a provider and checks rights against current user memberships/commands.
- If the required right is not authorized, listener is skipped and original event gets `UNAUTHORIZED`.
- Do not add `right` to `system:authorize` listeners (would create an authorization loop).

## Manifest Workflow (Merged)

1. Update module `zolinga.json` sections relevant to the change.
- `listen` for new handlers.
- `emit` when handler logic starts dispatching new events.
- `autoload` for new class namespaces.
- `dependencies` when relying on other modules.
2. Validate merged manifest output in `data/system/system.cache.json`.
3. Bump module minor version when needed to force manifest cache refresh.

## Handler Implementation Checklist

- Add listener entry in module `zolinga.json`.
- Implement class with `ListenerInterface`.
- Add typed event parameter matching actual emitter class.
- Set event status when handler meaningfully handled (`OK`, `NOT_FOUND`, etc.).
- If handler logic uses services from other modules, declare those modules in `dependencies` in `zolinga.json`.
- If handler assumes listeners/providers from other modules are available, declare those modules in `dependencies` too.
- Update `emit` section if code starts dispatching new events.
- **Always** create or update WIKI documentation for every new handler/event — not only CLI and CMS content-tag handlers. Place the doc at `{module}/wiki/ref/event/{event:name → path}.md` (replace `:` with `/`), e.g. event `ai:text:generated` → `{module}/wiki/ref/event/ai/text/generated.md`. Document: purpose, origin, request/response fields, emitter location, and a usage example.

## References

- `system/wiki/Zolinga Core/Manifest File.md`
- `system/wiki/Zolinga Core/Events and Listeners.md`
- `system/wiki/Zolinga Core/Events and Listeners/Event Authorization.md`
- `system/wiki/Zolinga Core/Events and Listeners/Remote Events.md`
- `system/wiki/ref/event/system/service/manifest.md`
- `system/src/Api.php`
- `system/src/Config/Atom/ListenAtom.php`
- `system/src/Config/Atom/EventMatchTrait.php`
- `system/src/Events/OriginEnum.php`
- `system/src/Events/RequestEvent.php`
- `system/src/Events/CliRequestResponseEvent.php`
- `system/src/Events/WebEvent.php`
- `system/src/Events/ContentEvent.php`
- `modules/zolinga-cms/src/ContentParser.php`
- `modules/zolinga-rms/src/UserService.php`
