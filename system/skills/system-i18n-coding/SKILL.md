---
name: system-i18n-coding
description: Use when writing localizable PHP code in Zolinga modules — dgettext/dngettext usage, domain naming, and context separators.
argument-hint: "<module-name>"
---

# Zolinga i18n: Writing Localizable PHP Code

## Use When

- Adding or modifying translatable strings in PHP code.
- Deciding which gettext domain to use.

## Core Rule

**Always use the module folder name as the gettext domain.** Never use `_()` or `gettext()` without a domain.

```php
// ✅ Correct — domain is the module folder name
echo dgettext('my-module', 'Hello, world!');

// ❌ Wrong — no domain, will use the default domain
echo _('Hello, world!');
```

## Functions

```php
// Single string
echo dgettext('my-module', 'Hello, world!');

// Plural — picks singular/plural based on $count
echo sprintf(dngettext('my-module', 'One apple', '%d apples', $count), $count);
```

## Context Separator

For ambiguous single-word strings, prepend context using `"\x04"` separator (end of transmission character).

```php
echo dgettext('my-module', "Verb: submit\x04Submit");
```

The `.po` file stores the full key including the `\x04` separator; translators see the context prefix.

## Do NOT

- Do not create `locale/` folders or `.po`/`.mo` files manually — use the CLI commands (see `zolinga-intl` module docs).
- Do not use `_()` or `gettext()` without domain — always use `dgettext()` with the module name as domain.

## Prerequisites

The `zolinga-intl` module must be installed. If not present:

```bash
bin/zolinga install --module=zolinga-intl
```

## Advanced Features

For JavaScript localization, static HTML translation, web component i18n, the `$api->locale` service, and the extract/compile pipeline, see the **`zolinga-intl`** module documentation:

- `modules/zolinga-intl/wiki/Zolinga Intl.md`
- `modules/zolinga-intl/wiki/Zolinga Intl/PHP.md`
- `modules/zolinga-intl/wiki/Zolinga Intl/JavaScript.md`
- `modules/zolinga-intl/wiki/Zolinga Intl/HTML.md`

## References

- `modules/zolinga-intl/wiki/Zolinga Intl.md` — primary i18n documentation
- `modules/zolinga-intl/src/LocaleService.php` — locale service implementation