---
name: system-override-mcp-redirect-page
description: Use when customizing the human-facing page browsers see at the MCP gateway URL (/mcp/) or at /dist/system/mcp-redirect.html - e.g. changing the wording, redirecting somewhere else, or replacing the card entirely.
argument-hint: "<module-name> [new destination or message]"
---

# Override the MCP Gateway Browser Page

## Use When

- Browsers landing on the MCP gateway endpoint must see different wording.
- Browsers must be redirected somewhere else instead of the home page.
- The whole landing card should be replaced with a site-specific one.
- Do not use this for API/agent behaviour - this page is shown to humans only.

## How the Page Works

The MCP gateway (`public/mcp/index.php`) is a JSON-RPC endpoint for AI agents. It also sends a `Refresh: 0; url=/dist/system/mcp-redirect.html` header, so a browser that opens the gateway URL is bounced to a plain HTML page:

```
https://example.com/mcp/  ->  https://example.com/dist/system/mcp-redirect.html
```

That page loads `/dist/system/js/web-components.js` and renders a single `<mcp-redirect>` element. The element is defined by the `system` module (`system/install/dist/web-components/mcp-redirect/`, priority `0.5`).

## Override Mechanism: Same Tag, Higher Priority

Web components in `zolinga.json` are merged into one flat list sorted by `priority` (descending). Registering the **same tag** in another module with a higher priority puts your entry first, and the loader resolves the tag to your module. Nothing in `system/` is edited.

Verify the merge:

```bash
grep -n -A4 '"tag": "mcp-redirect"' public/data/system/web-components.json
```

You should see two entries for the tag, yours first:

```json
{ "tag": "mcp-redirect", "module": "/dist/my-module/web-components/mcp-redirect/mcp-redirect.js", "priority": 0.6, ... },
{ "tag": "mcp-redirect", "module": "/dist/system/web-components/mcp-redirect/mcp-redirect.js", "priority": 0.5, ... }
```

## Workflow

1. Create the component files under `<module>/install/dist/web-components/mcp-redirect/`:

```
mcp-redirect.js     # component class
mcp-redirect.html   # template
mcp-redirect.css    # styles (optional)
mcp-redirect.md     # component docs
```

2. Write `mcp-redirect.js`. Use `WebComponentIntl` so localized templates are picked up when `<html lang>` is not `en-US`:

```javascript
import WebComponent from '/dist/zolinga-intl/js/web-component-intl.js';

export default class McpRedirect extends WebComponent {
    static REDIRECT_URL = '/agent-setup';

    constructor() {
        super();
        this.ready(this.#init());
    }

    async #init() {
        await this.loadContent(import.meta.url.replace('.js', '.html'), {
            mode: 'closed'
        });
        location.replace(McpRedirect.REDIRECT_URL);
    }
}
```

Use `location.replace()` so the gateway URL does not stay in browser history.

3. Write `mcp-redirect.html`. Keep it tiny - it is visible for a fraction of a second. Always include a no-script fallback link with the same destination:

```html
<html>

<head>
    <link rel="stylesheet" href="mcp-redirect.css">
    <meta name="gettext" content="translate">
    <meta charset="UTF-8">
</head>

<body>
    <div class="card">
        <p gettext="my-module:.">Redirecting you to the agent setup guide...</p>
        <p><a href="/agent-setup" gettext="my-module:.">Click here if you are not redirected.</a></p>
    </div>
</body>

</html>
```

The gettext domain must be the module name (`my-module`), not `system`.

4. Register the tag in the module's `zolinga.json` with a priority above `0.5`:

```json
"webComponents": [
    {
        "tag": "mcp-redirect",
        "description": "Overrides the system mcp-redirect card and sends browsers to the agent setup guide.",
        "module": "web-components/mcp-redirect/mcp-redirect.js",
        "priority": 0.6
    }
]
```

5. Bump the module `version` in `zolinga.json` and add a `CHANGELOG.md` entry.

6. Rescan manifests:

```bash
bin/zolinga
```

7. Verify:

```bash
grep -n -A4 '"tag": "mcp-redirect"' public/data/system/web-components.json
curl -sk -o /dev/null -w '%{http_code}\n' https://example.com/dist/my-module/web-components/mcp-redirect/mcp-redirect.html
```

`install/dist` is symlinked to `public/dist/<module>/`, so no copy step is needed.

## Pitfalls

- **Priority must be above `0.5`.** Equal priority is decided by tag name, so the system entry wins. Use `0.6` or higher.
- **Keep the tag name `mcp-redirect`.** Any other name creates a second, unused element and the system card still renders.
- **Cached manifest.** `/data/system/web-components.json` is served with `cache-control: max-age=3600`. A browser that loaded the old manifest continues to use the system component until the cache expires. Test with a hard reload or a fresh browser profile; the version bump covers real visitors.
- **Missing destination.** The redirect target is a plain relative URL. If the page does not exist yet, visitors land on the site 404 page - acceptable while the content is being written, but verify the destination before release.
- **Do not add scripts to the template.** `loadContent()` defaults to `allowScripts: false`. Keep the redirect logic in the component class.
- **Do not edit `system/`.** The core card stays as a fallback for installations without the override.

## References

- `system/install/dist/web-components/mcp-redirect/mcp-redirect.js` - core component being overridden
- `system/install/dist/mcp-redirect.html` - wrapper page served by the gateway
- `public/mcp/index.php` - gateway entry point that emits the `Refresh` header
- `system/skills/system-web-components/SKILL.md` - loadContent, priority, and naming rules
- `system/skills/system-authoring-manifest/SKILL.md` - `webComponents` manifest section
- `system/skills/system-create-skill/SKILL.md` - skill placement and naming
