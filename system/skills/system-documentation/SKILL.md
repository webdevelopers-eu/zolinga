---
name: system-documentation
description: Use when writing module or core documentation, including required locations and mandatory docs for CLI events and CMS content tags.
argument-hint: "<module-name> [event-or-topic]"
---

# Zolinga Documentation

## Use When

- Adding or updating docs for features, events, or content tags.
- Documenting CLI-origin events.

## Workflow

1. Place module docs under `modules/<module-name>/wiki/`.
2. Place core docs under `system/wiki/Zolinga Core/`.
3. Follow formatting and style guidance from `system/wiki/Zolinga Core/WIKI.md`.
4. Focus on practical usage and short actionable examples.
5. For `cli` origin events, create docs at:
`modules/<module-name>/wiki/ref/event/<event-name-with-colons-replaced-by-slashes>.md`
6. For content tags, create/update:
`modules/<module-name>/wiki/ref/event/cms/content/<tagName>.md`

## CLI Event Docs Must Include

- Supported request parameters.
- Command-line usage syntax.
- Practical invocation examples.

## Documentation Abstract

- Start with `WIKI.md` to follow authoring format and writing conventions.
- Use `Zolinga Core.md` as a topic map to select canonical reference pages.
- Use wiki event reference pages when documenting wiki behavior itself.

## References

- `system/wiki/Zolinga Core/WIKI.md`
- `system/wiki/Zolinga Core.md`
- `system/wiki/Tutorials.md`
- `system/wiki/ref/event/wiki/article.md`
- `system/wiki/ref/event/wiki/toc.md`
- `system/wiki/ref/event/wiki/search.md`
