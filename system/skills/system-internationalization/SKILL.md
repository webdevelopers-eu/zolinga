---
name: system-internationalization
description: Use when implementing, configuring, or troubleshooting internationalization in Zolinga — including PHP gettext, JavaScript gettext, static HTML translation, web-component localization, locale service, and the extract/compile CLI workflow.
argument-hint: "<module-name> [scope:php|js|html|web-component|config]"
---

# Zolinga Internationalization

## Use When

- Adding or modifying translatable strings in PHP, JavaScript, or HTML.
- Setting up a new locale/language for a module or the whole project.
- Running gettext extract or compile commands.
- Creating or updating web components that need localized HTML templates.
- Troubleshooting missing translations, fuzzy entries, or locale detection.
- Configuring `intl.locales` in `config/global.json`.
- Understanding the full extract → translate → compile pipeline.

## Architecture Overview

Zolinga uses GNU gettext as its i18n backbone. The `zolinga-intl` module provides:

- **`$api->locale`** service — current locale, language detection, gettext initialization.
- **PHP gettext** — `dgettext()` / `dngettext()` at runtime.
- **JavaScript gettext** — `gettext()` / `ngettext()` imported from `/dist/zolinga-intl/gettext.js?{MODULE}`.
- **Static HTML translation** — `<meta name="gettext" content="translate"/>` + `gettext` attribute; compiler generates `*.{lang-REGION}.html` files.
- **Web Component i18n** — `WebComponentIntl` base class auto-loads localized HTML templates.
- **CLI commands** — `bin/zolinga gettext:extract` and `bin/zolinga gettext:compile`.

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

- Do not create `locale/` folders or `.po`/`.mo` files manually — use the CLI commands.
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
2. Fetches `/dist/{module}/locale/{lang}.json` (e.g. `/dist/my-module/locale/cs-CZ.json`).
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

### Compilation Output

After `bin/zolinga gettext:compile`, translated files appear alongside the source:

```
📁 my-module/
    📁 install/dist/
        📄 index.html              ← source (meta: translate)
        📄 index.cs-CZ.html        ← auto-generated (meta: replace)
        📄 index.de-DE.html        ← auto-generated (meta: replace)
```

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

## CLI Commands

### `bin/zolinga gettext:extract [--module={MODULE}]`

Extracts translatable strings from PHP, JavaScript, and HTML files into `.po` files.

**What it does:**
1. Scans `{MODULE}/src/**/*.php` for `dgettext()`, `dngettext()` calls.
2. Scans `{MODULE}/install/dist/**/*.js` for `gettext()`, `ngettext()`, `__()`, `_n()` calls.
3. Scans `{MODULE}/**/*.html` for `gettext` attributes (only files with `<meta name="gettext" content="translate"/>`).
4. Generates/updates `{MODULE}/locale/messages.pot` (template).
5. Creates or updates `{MODULE}/locale/{LOCALE}.po` for each configured locale using `msginit`/`msgmerge`.
6. Also generates `{MODULE}/install/dist/locale/messages.pot` for JavaScript-specific extraction.

**Without `--module`**: processes all modules that have a `locale/` directory.

**Requirements**: `xgettext`, `msginit`, `msgmerge` must be available on the system. PHP `gettext` extension must be loaded.

### `bin/zolinga gettext:compile [--module={MODULE}]`

Compiles `.po` files into runtime formats and generates translated HTML.

**What it does:**
1. Compiles `{MODULE}/locale/{LOCALE}.po` → `{MODULE}/locale/{LOCALE}/LC_MESSAGES/{MODULE}.mo` (binary for PHP).
2. Reinitializes `$api->locale->initGettext()` so PHP picks up new `.mo` files.
3. Translates HTML files marked with `<meta name="gettext" content="translate"/>` → generates `*.{lang-REGION}.html` files.
4. Merges `{MODULE}/locale/{LOCALE}.po` with `{MODULE}/install/dist/locale/messages.pot` → generates `{MODULE}/install/dist/locale/{lang-REGION}.json` (JSON dictionary for JavaScript).

**Without `--module`**: processes all modules.

**Warnings**:
- If `.po` files contain `fuzzy` entries, compilation logs an error — review and remove the `fuzzy` keyword from correct translations.
- After compiling, you may need to restart PHP for `.mo` changes to take effect (OPcache).

## File Structure Reference

```
📁 my-module/
    📁 locale/                              ← PHP gettext domain root
        📄 LINGUAS                          ← list of locales (one per line)
        📄 messages.pot                     ← extracted template
        📄 en_US.po                         ← English translations
        📄 cs_CZ.po                         ← Czech translations
        📁 en_US/LC_MESSAGES/
            📄 my-module.mo                  ← compiled binary (PHP)
        📁 cs_CZ/LC_MESSAGES/
            📄 my-module.mo                  ← compiled binary (PHP)
    📁 install/dist/
        📁 locale/                           ← JS gettext domain root
            📄 LINGUAS
            📄 messages.pot                  ← JS-specific template
            📄 en-US.json                    ← compiled JSON (JS)
            📄 cs-CZ.json                    ← compiled JSON (JS)
            📄 README.txt                    ← "do not edit" notice
        📄 my-page.html                      ← source HTML (meta: translate)
        📄 my-page.cs-CZ.html                ← compiled translation
        📁 web-components/my-component/
            📄 my-component.html             ← source template
            📄 my-component.cs-CZ.html       ← localized template
```

## Complete Workflow

### 1. Configure Locales

Edit `config/global.json`:

```json
{"intl": {"locales": ["en_US", "cs-CZ", "de-DE"]}}
```

### 2. Mark Translatable Strings

- **PHP**: `dgettext('my-module', 'String')`
- **JS**: `import {gettext} from "/dist/zolinga-intl/gettext.js?my-module"; gettext("String")`
- **HTML**: `<meta name="gettext" content="translate"/>` + `gettext="."` attributes

### 3. Extract

```bash
bin/zolinga gettext:extract --module=my-module
```

Creates/updates `.po` files in `my-module/locale/`.

### 4. Translate

Edit `my-module/locale/cs_CZ.po` (and other locale files). Use Poedit or any text editor. Remove `#, fuzzy` markers from correct translations.

### 5. Compile

```bash
bin/zolinga gettext:compile --module=my-module
```

Generates:
- `.mo` files for PHP runtime
- `.json` dictionaries for JavaScript
- `.{lang-REGION}.html` translated HTML files

### 6. Verify

- PHP: `dgettext('my-module', 'String')` should return the translated string.
- JS: Check browser console for `gettext()` output or catalog fetch errors.
- HTML: Open the `.{lang-REGION}.html` file in a browser.

## Troubleshooting

| Problem | Cause | Fix |
|---------|-------|-----|
| PHP returns English string | `.mo` file missing or stale | Run `gettext:compile`; restart PHP |
| JS returns English string | `.json` dictionary missing or 404 | Check `{MODULE}/install/dist/locale/{lang}.json` exists; run `gettext:compile` |
| "fuzzy translations" error on compile | `.po` file has `#, fuzzy` markers | Edit `.po` file, remove `fuzzy` keyword from correct entries |
| Locale not supported by OS | OS locale not generated | Run `locale -a` to check; generate locale on OS level |
| `bindtextdomain` warning | `locale/` directory missing in module | Create `locale/` dir or skip if module has no translations |
| Cherry-pick file not updating new strings | New strings need plain `gettext` attr (no hash) | Add English text with `gettext="."` attr; compiler will translate and add hash |
| JS catalog 404 | Module not installed/symlinked to `public/dist/` | Run module install or check symlink |
| `xgettext` not found | Missing gettext tools | Install: `apt install gettext` |

## Types Provided

- `Zolinga\Intl\Types\CountryEnum` — ISO 3166-1 alpha-2 country codes as backed enum (int values).
- `Zolinga\Intl\Types\CountryGroupsEnum` — `EU`, `EFTA`, `BX` group constants.

## References

- `modules/zolinga-intl/wiki/Zolinga Intl.md` — primary i18n documentation
- `modules/zolinga-intl/wiki/Zolinga Intl/PHP.md` — PHP translation details
- `modules/zolinga-intl/wiki/Zolinga Intl/JavaScript.md` — JS translation details
- `modules/zolinga-intl/wiki/Zolinga Intl/HTML.md` — static HTML translation details
- `modules/zolinga-intl/wiki/ref/event/gettext/extract.md` — CLI extract event docs
- `modules/zolinga-intl/wiki/ref/event/gettext/compile.md` — CLI compile event docs
- `modules/zolinga-intl/src/LocaleService.php` — locale service implementation
- `modules/zolinga-intl/src/GettextCli.php` — CLI handler for extract/compile
- `modules/zolinga-intl/src/Gettext/Extractor.php` — string extraction logic
- `modules/zolinga-intl/src/Gettext/Compiler.php` — PO→MO + HTML compilation
- `modules/zolinga-intl/src/Gettext/JavascriptCompiler.php` — PO→JSON compilation
- `modules/zolinga-intl/src/Gettext/JavascriptExtractor.php` — JS-specific extraction
- `modules/zolinga-intl/install/dist/gettext.js` — front-end gettext module
- `modules/zolinga-intl/install/dist/js/web-component-intl.js` — localized WebComponent base class