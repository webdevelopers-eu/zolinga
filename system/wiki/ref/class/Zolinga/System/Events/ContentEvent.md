# ContentEvent

`\Zolinga\System\Events\ContentEvent` is the abstract base class for all content generation events. It is not dispatched directly; instead, `public/index.php` dispatches concrete subclasses based on the MIME type determined by the preceding `system:content:preflight` event.

## Subclasses

| Class | Event name | MIME type |
|-------|-----------|----------|
| `\Zolinga\System\Events\Content\HtmlContentEvent` | `system:content:html` | `text/html` |
| `\Zolinga\System\Events\Content\JsonContentEvent` | `system:content:json` | `application/json` |
| `\Zolinga\System\Events\Content\TextContentEvent` | `system:content:text` | `text/plain` |
| `\Zolinga\System\Events\Content\PreflightEvent` | `system:content:preflight` | (determines MIME type for the above) |

All content events are dispatched with origin *remote* and are stoppable.

For more information about page generation refer to [Processing Page Content](:Zolinga Core:Running the System:Page Request:Processing Page Content).