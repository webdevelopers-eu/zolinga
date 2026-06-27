# Content Events

When a page is requested, `public/index.php` dispatches content generation in two phases:

1. **Preflight** — `system:content:preflight` (`\Zolinga\System\Events\Content\PreflightEvent`, origin *remote*). Handlers inspect the request and set `$event->mimeType` (a `\Zolinga\System\Types\ContentMimeTypesEnum`) to determine the response format. They can also rewrite `$event->path` and `$event->canonicalPath`, or prevent content generation by setting a non-OK status.

2. **Content** — Based on the MIME type from the preflight event, `index.php` instantiates and dispatches the appropriate content event:

   | MIME type | Event class | Event name |
   |----------|-------------|------------|
   | `text/html` (default) | `\Zolinga\System\Events\Content\HtmlContentEvent` | `system:content:html` |
   | `application/json` | `\Zolinga\System\Events\Content\JsonContentEvent` | `system:content:json` |
   | `text/plain` | `\Zolinga\System\Events\Content\TextContentEvent` | `system:content:text` |

   All content event classes extend the abstract `\Zolinga\System\Events\ContentEvent` and are dispatched with origin *remote*.

   This is not limited to HTML pages. Any module can serve JSON or plain text from any URL (e.g. `/.well-known/*` discovery endpoints, `robots.txt`) by combining a `system:content:preflight` listener (to set the MIME type) with a `system:content:json` or `system:content:text` listener (to provide the content). See [Processing Page Content](:Zolinga Core:Running the System:Page Request:Processing Page Content) for a worked example.

For more information about page generation refer to [Processing Page Content](:Zolinga Core:Running the System:Page Request:Processing Page Content).

