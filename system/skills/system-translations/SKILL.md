---
name: system-translations
description: Use when adding localized strings in PHP or HTML, including gettext usage patterns and context-aware translation strings for Zolinga Intl.
argument-hint: "<module-name> [string-scope]"
---

# Zolinga Translations

## Use When

- Adding or changing translatable strings in PHP or HTML.
- Adding context-aware translation keys.

## Workflow

1. In PHP, use `dgettext('<module-name>', '<string>')` (or `dngettext(...)` for plural forms).
2. Do not create gettext files or `locale/` folders manually.
3. In HTML, use `gettext` attribute and related document translation metadata.
4. For ambiguous single-word labels, prepend context using `GETTEXT_CTX_END`.

## Example

`dgettext('system-intl', 'Confirm form submission' . GETTEXT_CTX_END . 'Send')`

## Documentation Abstract

- Start with core rendering/runtime docs to understand where translated text is emitted.
- Use content and request processing docs when adding translated HTML output.
- Use global wiki authoring guidance for translation examples in docs.

## References

- `modules/zolinga-intl/wiki/Zolinga Intl.md` — primary translation documentation
- `system/wiki/Zolinga Core/Running the System.md`
- `system/wiki/Zolinga Core/Running the System/Page Request.md`
- `system/wiki/Zolinga Core/Running the System/Page Request/Processing Page Content.md`
- `system/wiki/ref/event/system/content.md`
- `system/wiki/Zolinga Core/WIKI.md`
