---
name: system-content-tags
description: Use when implementing CMS content tag handlers (cms:content:tagName), including listener contracts, DOM manipulation rules, and required wiki docs.
argument-hint: "<module-name> <tag-name>"
---

# Zolinga Content Tags

## Use When

- Creating or updating `cms:content:<tagName>` handlers.
- Updating content rendering behavior with system-cms.

## Workflow

1. Register handler for `cms:content:<tagName>` in the `listen` section of module `zolinga.json`.
2. Use `internal` origin for content tag handlers.
3. Use listener class naming with `Listener` suffix.
4. Implement `ListenerInterface` and accept a typed event parameter in `handle`.
5. For content tags, accept `ContentElementEvent $event` and set status via `$event->setStatus(...)`.
6. Manipulate content with DOM API (and `DOMXPath` when needed).
7. Create/update documentation at `modules/<module-name>/wiki/ref/event/cms/content/<tagName>.md`.
8. Test content tags from CLI with `bin/zolinga process:content --input=page.html` (see `system/wiki/ref/event/process/content.md`).

## Documentation Abstract

- Use `Events and Listeners.md` for listener wiring and event lifecycle.
- Use page-content processing docs to understand where content handlers run.
- Use event class references for status/state handling patterns.

## References

- `system/wiki/Zolinga Core/Events and Listeners.md`
- `system/wiki/Zolinga Core/Running the System/Page Request/Processing Page Content.md`
- `system/wiki/ref/class/Zolinga/System/Events/ContentEvent.md`
- `system/wiki/ref/event/system/content.md`
- `system/wiki/Zolinga Core/WIKI.md`
