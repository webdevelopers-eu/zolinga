---
name: system-i18n-coding
description: Use when writing localizable code in Zolinga — PHP dgettext/dngettext, JS gettext/ngettext imports, static HTML gettext attributes and texts, WebComponentIntl templates, and locale service usage.
argument-hint: "<module-name> [scope:php|js|html|web-component]"
---

# Zolinga i18n: Writing Localizable Code

## Use When

- Adding or modifying translatable strings in PHP, JavaScript, or HTML.
- Creating or updating web components that need localized HTML templates.
- Using the `$api->locale` service for locale detection or localized file paths.
- Configuring `intl.locales` in `config/global.json`.
- Deciding which translation approach to use (runtime gettext vs static HTML).

## Architecture Overview

Zolinga uses GNU gettext as its i18n backbone. The `zolinga-intl` module provides:

- **`$api->locale`** service — current locale, language detection, gettext initialization.
- **PHP gettext** — `dgettext()` / `dngettext()` at runtime.
- **JavaScript gettext** — `gettext()` / `ngettext()` imported from `/dist/zolinga-intl/gettext.js?{MODULE}`.
- **Static HTML translation** — `<meta name="gettext" content="translate"/>` + `gettext` attribute; compiler generates `*.{lang-REGION}.html` files.
- **Web Component i18n** — `WebComponentIntl` base class auto-loads localized HTML templates.

For the extract → translate → compile pipeline, see the `system-i18n-translation` skill.

## Locale Configuration

Locales are configured in `config/global.json`:

```json
{
  "intl": {
    "locales": ["en_US", "cs-CZ"]
  }
}
```

The first locale is the default. The `zolinga-intl` module's `zolinga.json` also has a default `config.intl.locales` of `["en_US"]`.

## `$api->locale` Service Properties

| Property | Format | Example |
|----------|--------|---------|
| `$api->locale->tag` | Canonicalized BCP 47 | `cs-CZ` |
| `$api->locale->locale` | `language_REGION` | `cs_CZ` |
| `$api->locale->jsLocale` | `language-REGION` | `cs-CZ` |
| `$api->locale->lang` | Primary language code | `cs` |
| `$api->locale->region` | Region code | `CZ` |

Read-only arrays on the service:
- `supportedTags`, `supportedLocales`, `supportedLangs`, `supportedLangNames`, `supportedLocaleNames`, `supportedRegionNames`

Language detection priority: `$_COOKIE['lang']` → `$_SESSION['lang']` → `Accept-Language` header → first configured locale.

Setting `$api->locale->locale = 'cs_CZ'` or `$api->locale->lang = 'cs'` reinitializes gettext for all domains.

### `getLocalizedFile()`

```php
$api->locale->getLocalizedFile('path/to/template.html');
// Returns path/to/template.cs-CZ.html if it exists, else original
```

## PHP Translations

### Functions

```php
// Single string — domain is the module folder name
echo dgettext('my-module', 'Hello, world!');

// Plural — picks singular/plural based on $count
echo sprintf(dngettext('my-module', 'There is one apple', 'There are %d apples', $count), $count);
```

### Context Separator

For ambiguous single-word strings, prepend context using `GETTEXT_CTX_END` (`\x04`):

```php
echo dgettext('my-module', 'Confirm form submission' . GETTEXT_CTX_END . 'Send');
```

The `.po` file will show the full key including context; translators see the context prefix.

### Do NOT

- Do not create `locale/` folders or `.po`/`.mo` files manually — use the CLI commands (see `system-i18n-translation` skill).
- Do not use `_()` or `gettext()` without domain — always use `dgettext()` with the module name as domain.

## JavaScript Translations

### Import and Usage

```javascript
import {gettext, ngettext} from "/dist/zolinga-intl/gettext.js?my-module";

console.log(gettext("Hello, world!"));
console.log(ngettext("One apple", "%s apples", 3, 3));
```

The `?my-module` query string sets the gettext domain. No `dgettext` needed — domain is bound at import time.

Aliases: `gettext` = `__`, `ngettext` = `_n`.

### How It Works

1. `gettext.js` reads the `lang` cookie (set by `$api->locale`).
2. Fetches `/dist/{module}/locale/{lang}.json` (e.g., `/dist/my-module/locale/cs-CZ.json`).
3. If `en-US`, skips fetch (source strings are English).
4. Initializes the `gettext.js` library with the fetched dictionary.

### JSON Dictionary Format

Located at `{MODULE}/install/dist/locale/{lang}.json`:

```json
{
  "": {
    "language": "cs",
    "plural-forms": "nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;"
  },
  "Hello, world!": "Ahoj, světe!",
  "One apple": ["Jedno jablko", "%s jablka", "%s jablek"]
}
```

## Static HTML Translation

For large blocks of text (legal docs, articles, email templates), use static HTML translation instead of runtime gettext.

### Marking HTML for Translation

Add `<meta name="gettext" content="translate"/>` to `<head>` and mark elements with the `gettext` attribute:

```html
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="gettext" content="translate"/>
    <title gettext=".">My Page Title</title>
    <meta name="description" content="My description" gettext="content"/>
  </head>
  <body>
    <h1 gettext=".">Hello, World!</h1>
    <img alt="Company logo" gettext="alt" src="logo.png"/>
    <p gettext="title ." title="Tooltip text">Paragraph with tooltip</p>
  </body>
</html>
```

### `gettext` Attribute Syntax

Value is whitespace-separated keywords: `[domain:]attribute|.[#hash]`

| Keyword | Meaning |
|---------|---------|
| `.` | Translate element's text content |
| `title` | Translate the `title` attribute |
| `alt` | Translate the `alt` attribute |
| `content` | Translate the `content` attribute (for `<meta>`) |
| `my-module:title` | Use domain `my-module` instead of default |
| `.#a3f2b1` | Hash suffix (added by compiler, not manually) |

Examples:
- `gettext="."` — translate text content only
- `gettext="title ."` — translate both `title` attribute and text content
- `gettext="alt"` — translate `alt` attribute only

### Translation Modes in Generated Files

The `<meta name="gettext" content="..."/>` in the **generated** file controls behavior on recompilation:

| Mode | Behavior |
|------|----------|
| `replace` | File is fully regenerated from source on every compile. **Default for new files.** Any manual edits are lost. |
| `cherry-pick` | Only elements with `gettext` attribute are updated; all other HTML is preserved. Good for large articles where translators need full control of layout. |
| (no meta) | File is ignored by compiler — fully manual maintenance. |

### Cherry-Pick Mode Details

In cherry-pick mode, `gettext` attributes include `#HASH` suffixes (e.g., `gettext=".#943a70 title#e52e5e"`) that map back to `.po` file entries. To add a new translatable string to a cherry-picked file, add the English text with a plain `gettext` attribute — the compiler will translate it and add the hash on next compile.

**Warning**: Cherry-pick mode does NOT sync structural changes (CSS, images, new elements) from the source file — only translatable strings are updated.

## Web Component Localization

### `WebComponentIntl` Base Class

Located at `/dist/zolinga-intl/js/web-component-intl.js`. Extends `WebComponent` with automatic locale-aware template loading.

```javascript
import WebComponentIntl from '/dist/zolinga-intl/js/web-component-intl.js';

export default class MyComponent extends WebComponentIntl {
    // rewriteURL() automatically inserts locale before file extension
    // e.g., template.html → template.cs-CZ.html (if lang != en-US)
}
```

### How It Works

`rewriteURL(url, type)` overrides the parent to insert the current `document.documentElement.lang` (e.g. `cs-CZ`) before the file extension:

- `template.html` → `template.cs-CZ.html`
- Only applies when `lang !== 'en-US'` and matches `xx-XX` format.
- Falls back to the original URL if the localized file doesn't exist.

### When to Use

- Use `WebComponentIntl` when your web component loads an HTML template via `loadContent()` that contains translatable static text.
- Use regular `WebComponent` + JS `gettext()` when your component builds text dynamically in JavaScript.

## Choosing the Right Approach

| Scenario | Approach |
|----------|----------|
| Short UI labels, buttons, messages | PHP `dgettext()` or JS `gettext()` |
| Large static content (articles, legal) | Static HTML with `gettext` attributes |
| Web component with HTML template | `WebComponentIntl` + compiled HTML |
| Web component with dynamic text | `WebComponent` + JS `gettext()` |
| Ambiguous single words | `GETTEXT_CTX_END` context separator |

## Types Provided

- `Zolinga\Intl\Types\CountryEnum` — ISO 3166-1 alpha-2 country codes as backed enum (int values).
- `Zolinga\Intl\Types\CountryGroupsEnum` — `EU`, `EFTA`, `BX` group constants.

## References

- `modules/zolinga-intl/wiki/Zolinga Intl.md` — primary i18n documentation
- `modules/zolinga-intl/wiki/Zolinga Intl/PHP.md` — PHP translation details
- `modules/zolinga-intl/wiki/Zolinga Intl/JavaScript.md` — JS translation details
- `modules/zolinga-intl/wiki/Zolinga Intl/HTML.md` — static HTML translation details
- `modules/zolinga-intl/src/LocaleService.php` — locale service implementation
- `modules/zolinga-intl/install/dist/gettext.js` — front-end gettext module
- `modules/zolinga-intl/install/dist/js/web-component-intl.js` — localized WebComponent base class