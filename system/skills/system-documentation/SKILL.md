---
name: system-documentation
description: Use when writing features, modules or modifying APIs in any way, including required locations and mandatory docs and CMS content tags.
argument-hint: "<module-name> [event-or-topic]"
---

# Zolinga Documentation

## Use When

- Adding or updating docs for features, events, or content tags.

## Workflow

1. Place module docs under `modules/<module-name>/wiki/`.
2. Place core docs under `system/wiki/Zolinga Core/`.
3. Follow formatting and style guidance from `system/wiki/Zolinga Core/WIKI.md`.
4. Focus on practical usage and short actionable examples.
5. For events, create docs at:
`modules/<module-name>/wiki/ref/event/<event-name-with-colons-replaced-by-slashes>.md`
6. For content tags, create/update:
`modules/<module-name>/wiki/ref/event/cms/content/<tagName>.md`
7. For API changes, update relevant wiki docs and add cross-references to new docs.
8. Consider updating appropriate `SKILL.md` files to reference new docs and ensure the skill's documentation abstract is up to date. If appropriate create a new skill for documentation if the change is large enough to warrant its own skill.

## Event Docs Must Include

- Supported request parameters.
- Usage syntax.
- Practical invocation examples.

## References

- `system/wiki/Zolinga Core/WIKI.md`
- `system/wiki/Zolinga Core.md`
- `system/wiki/Tutorials.md`
- `system/wiki/ref/event/wiki/article.md`
- `system/wiki/ref/event/wiki/toc.md`
- `system/wiki/ref/event/wiki/search.md`
