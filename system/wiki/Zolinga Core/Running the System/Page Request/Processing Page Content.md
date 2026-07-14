Priority: 0.4

# Processing Page Content

Requests are routed to `./public/index.php` when no static file exists for the requested URL.

After the [POST and GET requests are processed](:Zolinga Core:Running the System:Page Request:Processing POST and GET), the `./public/index.php` script dispatches content generation in two phases: a **preflight** event followed by a **content** event.

## Phase 1: Preflight Event

First, `index.php` dispatches a `\Zolinga\System\Events\Content\PreflightEvent` of type `system:content:preflight` with the origin set to *remote*. The preflight event gives handlers a chance to inspect the request before any content is generated. Handlers can:

- **Determine the content type** by setting `$event->mimeType` to one of `\Zolinga\System\Types\ContentMimeTypesEnum` (e.g. `text/html`, `application/json`, `text/plain`). The default is `text/html`.
- **Rewrite the path** by setting `$event->path` (e.g. for URL aliasing or locale prefix stripping). The rewritten path is then passed to the content event.
- **Set the canonical path** via `$event->canonicalPath`.
- **Prevent content generation** by setting a non-OK status on the event, which causes `index.php` to skip the content event and send the appropriate error response.

The preflight event is stoppable. A high-priority handler that has determined the content type can call `$event->stopPropagation()` to prevent lower-priority preflight handlers from overriding the decision.

## Phase 2: Content Event

Based on `$preflightEvent->mimeType`, `index.php` instantiates the appropriate content event class and dispatches it:

| MIME type | Event class | Event name |
|----------|-----------|------------|
| `text/html` (default) | `\Zolinga\System\Events\Content\HtmlContentEvent` | `system:content:html` |
| `application/json` | `\Zolinga\System\Events\Content\JsonContentEvent` | `system:content:json` |
| `text/plain` | `\Zolinga\System\Events\Content\TextContentEvent` | `system:content:text` |

The content event receives the (possibly rewritten) `$event->path` and `$event->canonicalPath` from the preflight event. All content event classes extend the abstract `\Zolinga\System\Events\ContentEvent`.

Any listeners attached to the content event will be executed in the order of their priority. Listeners are expected to populate the `$event->content` property with content appropriate for the event type (e.g. a `DOMDocument` for HTML, a JSON payload for JSON, plain text for text) to be sent to the browser.

If a handler produces its own output such as binary data or images, it should call `$event->preventDefault()` to prevent the default output of `$event->content`. Optionally, $event->stopPropagation() can be called immediately after to prevent further processing of the event if the default output is not intended.

It is recommended to avoid directly outputting anything from the handler, unless it is necessary. Instead, store the output in the `$event->content` property. This allows for further filters and content modifications by other lower priority handlers.

The `$event->path` property is writable, allowing handlers with higher priority to rewrite the path. Subsequent handlers can then react to the new path. Therefore, it is advised to use `$event->path` to detect the page URL rather then reading `$_SERVER['REQUEST_URI']` directly.

When the event status is `$event->status == ContentEvent::STATUS_UNDETERMINED`, it indicates that the content has not been added by any handler yet. If the status is `ContentEvent::STATUS_OK`, it means that the content has already been added by the previous handler, and subsequent handlers should not replace it. Instead, they can only modify or filter the content. So when you want to add content, check if the status is `ContentEvent::STATUS_UNDETERMINED`, and if yes and you have added the content, set the status by calling `$event->setStatus($event::STATUS_OK, "Content added.")`.

# Example

`zolinga.json`

```json
{
    "listen": [
        {
            "event": "system:content:html",
            "class": "Example\\ContentHandler",
            "method": "onContent",
            "origin": ["remote"],
            "priority": 0.6
        }
    ]
}
```

`ContentHandler.php`

```php
namespace Example;
use Zolinga\System\Events\Content\HtmlContentEvent;
use Zolinga\System\Events\ListenerInterface;
use \DOMDocument;

class ContentHandler implements ListenerInterface
{
    public function onContent(HtmlContentEvent $event)
    {
        if ($event->status !== HtmlContentEvent::STATUS_UNDETERMINED) {
            return; // already filled with content by other listener
        }

        $path = $event->path;
        /** @var \DOMDocument $content */
        $content = $event->content;

        if ($path == "/") {
            $content->loadHTML("<html><body><h1>Welcome to Zolinga</h1></body></html>");
            $event->setStatus($event::STATUS_OK, "Content added.");
        }
    }
}
```

## Listening to the Preflight Event

To influence which content event is dispatched (e.g. serve JSON for API paths), listen to `system:content:preflight`:

```json
{
    "listen": [
        {
            "event": "system:content:preflight",
            "class": "Example\\ApiPreflightHandler",
            "method": "onPreflight",
            "origin": ["remote"],
            "priority": 0.8
        }
    ]
}
```

```php
namespace Example;
use Zolinga\System\Events\Content\PreflightEvent;
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Types\ContentMimeTypesEnum;

class ApiPreflightHandler implements ListenerInterface
{
    public function onPreflight(PreflightEvent $event): void
    {
        // Serve JSON for /api/* paths
        if (str_starts_with($event->path, '/api/')) {
            $event->mimeType = ContentMimeTypesEnum::APPLICATION_JSON;
        }
    }
}
```

## Serving Non-HTML Content (JSON, Text)

The preflight + content event flow is not limited to HTML pages. Any module can serve JSON or plain text from any URL by combining a `system:content:preflight` listener (to set the MIME type) with a `system:content:json` or `system:content:text` listener (to provide the content). This is how `.well-known/` discovery endpoints, `robots.txt`, `manifest.json`, and similar non-page responses are meant to be served.

### How It Works

1. A preflight handler matches the request path and sets `$event->mimeType` to `application/json` or `text/plain`.
2. `index.php` dispatches `system:content:json` (or `system:content:text`) instead of `system:content:html`.
3. A content handler for that event populates the response:
   - **JSON** — set `$event->response` (a `mixed` value: array, object, scalar). `getOutput()` JSON-encodes it.
   - **Text** — set `$event->response` (a `string`). `getOutput()` returns it as-is.
4. `index.php` sends the output with the correct `Content-Type` header.

Multiple modules can contribute to the same JSON/text endpoint. Lower-priority handlers can read `$event->response` (already populated) and add to it, since the status-based "first writer wins, others modify" pattern applies the same way as with HTML.

### Example: Serving `/.well-known/oauth-protected-resource` as JSON

This is a real use case: an OAuth module needs to serve a JSON document at `/.well-known/oauth-protected-resource` so MCP clients can discover the authorization server. The module contributes this via two listeners — no static file, no custom endpoint in `install/dist/`.

`zolinga.json`

```json
{
    "listen": [
        {
            "event": "system:content:preflight",
            "class": "Zolinga\\OAuth\\PreflightListener",
            "method": "onPreflight",
            "origin": ["remote"],
            "priority": 0.8
        },
        {
            "event": "system:content:json",
            "class": "Zolinga\\OAuth\\WellKnownListener",
            "method": "onJson",
            "origin": ["remote"],
            "priority": 0.8
        }
    ]
}
```

`PreflightListener.php`

```php
namespace Zolinga\OAuth;
use Zolinga\System\Events\Content\PreflightEvent;
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Types\ContentMimeTypesEnum;

class PreflightListener implements ListenerInterface
{
    public function onPreflight(PreflightEvent $event): void
    {
        // Tell index.php to dispatch system:content:json instead of html
        if ($event->path === '/.well-known/oauth-protected-resource') {
            $event->mimeType = ContentMimeTypesEnum::APPLICATION_JSON;
        }
    }
}
```

`WellKnownListener.php`

```php
namespace Zolinga\OAuth;
use Zolinga\System\Events\Content\JsonContentEvent;
use Zolinga\System\Events\ListenerInterface;

class WellKnownListener implements ListenerInterface
{
    public function onJson(JsonContentEvent $event): void
    {
        if ($event->path !== '/.well-known/oauth-protected-resource') {
            return;
        }
        if ($event->status !== $event::STATUS_UNDETERMINED) {
            return; // another handler already filled this
        }

        global $api;
        $baseURL = rtrim($api->config['baseURL'] ?? '', '/');

        $event->response = [
            'resource' => $baseURL . '/mcp',
            'authorization_servers' => [$baseURL . '/.well-known/oauth-authorization-server'],
            'scopes_supported' => ['mcp:tools'],
            'bearer_methods_supported' => ['header'],
        ];
        $event->setStatus($event::STATUS_OK, 'OAuth PRM served.');
    }
}
```

### Example: Serving `robots.txt` as Plain Text

The same pattern works for `text/plain`. A preflight handler sets the MIME type to `text/plain` for `/robots.txt`, and a `system:content:text` handler sets `$event->response` to the robots content. This lets modules contribute rules to a single dynamic `robots.txt` without a static file.

# Related
{{Running the System}}