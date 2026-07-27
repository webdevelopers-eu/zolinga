# &lt;mcp-redirect&gt;

Friendly card shown to browsers that land on the `/mcp/` AI agent gateway URL.
Explains that the endpoint is for machines, not humans, and provides a link
back to the home page.

## Syntax

```html
<mcp-redirect></mcp-redirect>
```

## Usage

Place on a page that includes the web-components loader:

```html
<script type="module" src="/dist/system/js/web-components.js"></script>
<mcp-redirect></mcp-redirect>
```

The component loads its own HTML template and CSS. Text content is
translatable via gettext attributes (domain `system`).

## Attributes

None.

## Slots

None.

## Events

Standard `web-component-ready` / `web-component-error` events.

## Localization

Extends `WebComponentIntl`. When `<html lang="cs-CZ">` is set, the loader
fetches `mcp-redirect.cs-CZ.html` instead of the default `mcp-redirect.html`.
Use `bin/zolinga gettext:extract --domains=system` to extract strings and
`bin/zolinga gettext:compile --domains=system` to compile localized copies.