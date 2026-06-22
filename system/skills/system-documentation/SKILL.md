---
name: system-documentation
description: Use when writing or updating user-facing wiki documentation for features, modules, events, or content tags. Guides a user-oriented writing style focused on tasks and outcomes rather than internal implementation.
argument-hint: "<module-name> [event-or-topic]"
---

# Zolinga Documentation

## Use When

- Adding or updating wiki docs for features, events, or content tags.
- Writing any article that an end user or site administrator will read.

## Audience

The primary reader is a **user** of the system - someone who wants to accomplish a task, configure a feature, or understand what something does and how to use it. They are NOT a programmer reading source code. Write for the person sitting in front of the wiki in the browser, not for the developer reading the class files.

Secondary audience (administrators, integrators) may need a few technical details - put those in a clearly marked "Technical Details" section at the end, not in the main flow.

## Writing Style - User First

- **Lead with the outcome.** The first sentence answers "What can I do with this?" not "What is this called internally?"
- **Write tasks, not APIs.** Structure articles around what the user wants to achieve: "How to ...", "Configuring ...", "Using ...". Avoid structuring around class names, method signatures, or event origins.
- **Plain language.** Short sentences. No jargon without a one-line explanation. If a term is unavoidable, link to its wiki page.
- **Show, don't tell.** Prefer a screenshot, a config snippet the user can copy, or a step-by-step numbered list over a paragraph of prose.
- **Keep code minimal and user-facing.** Show the config block, the CLI command, or the UI steps the user performs. Do NOT show PHP class definitions, method bodies, listener wiring, or internal event dispatch mechanics unless the article is explicitly in the `ref/` reference branch for developers.
- **No implementation details in the main flow.** Class names, method names, event origins, priorities, and file paths belong in an optional "Technical Details" section at the bottom - or in the `ref/` reference article - never in the opening paragraphs.
- **One article, one goal.** If a feature has several user tasks, split them into sub-pages rather than one long page.

## Workflow

1. Place module docs under `modules/<module-name>/wiki/`.
2. Place core docs under `system/wiki/Zolinga Core/`.
3. Follow formatting and structure guidance from `system/wiki/Zolinga Core/WIKI.md`.
4. Write in the user-first style described above.
5. For events, create docs at:
`modules/<module-name>/wiki/ref/event/<event-name-with-colons-replaced-by-slashes>.md`
6. For content tags, create/update:
`modules/<module-name>/wiki/ref/event/cms/content/<tagName>.md`
7. For API changes, update relevant wiki docs and add cross-references to new docs.
8. Consider updating appropriate `SKILL.md` files to reference new docs and ensure the skill's documentation abstract is up to date. If appropriate create a new skill for documentation if the change is large enough to warrant its own skill.

## Event Docs - User-Oriented Structure

Event reference articles (under `ref/event/`) should still be useful to a user who wants to trigger or call that event, not to a programmer studying its internals. Structure them as:

1. **What this does** - one or two sentences in plain language describing the user-visible result.
2. **How to use it** - the actual command, config, or UI action the user performs. Show the real invocation (CLI command, config snippet, or front-end call) as a copy-pasteable example.
3. **Parameters** - a table of inputs the user can provide, described by what they mean to the user, not by their PHP type or internal field name.
4. **Result** - what the user gets back, described as data or behavior, not as class properties.
5. **Technical Details (optional, at the end)** - event name, origin, right required, implementing class. Only for readers who need to wire something up themselves.

## Content Tag Docs

Content tag articles (under `ref/event/cms/content/`) should explain:

1. **What it displays** - what the user sees on the page when this tag is used.
2. **How to use it** - the exact tag syntax to put in a CMS page, with a minimal working example.
3. **Options** - attributes or parameters, described by their visible effect.
4. **Technical Details (optional, at the end)** - handler class, manifest entry.

## What NOT to Put in User-Facing Articles

- PHP class definitions, method bodies, `use` statements.
- Internal event dispatch mechanics (`$api->dispatchEvent`, listener priorities, origin arrays).
- File paths into `src/` directories.
- Manifest (`zolinga.json`) snippets - those belong in developer reference, not user docs.
- Implementation notes about how something works under the hood.

## References

- `system/wiki/Zolinga Core/WIKI.md` - wiki structure and syntax
- `system/wiki/Zolinga Core/Markdown Syntax.md` - formatting reference
- `system/wiki/Zolinga Core.md`
- `system/wiki/Tutorials.md`
